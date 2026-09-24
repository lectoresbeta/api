<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Community;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquear a alguien (`FEAT-COM-034`).
 *
 * **Lo que se defiende aquí no es el bloqueo: es que `Community` no lo
 * aplique.** Este contexto guarda la relación, deshace los seguimientos
 * —suyos también— y publica el hecho. Que el bloqueado pierda el acceso a una
 * obra inédita lo decide `Reading`, y que deje de poder comentar, `User`.
 * Ninguno de los dos pregunta a `Community` nada: cada uno reacciona al hecho
 * con lo que sabe.
 *
 * Y la regla incómoda, que la ficha dice de frente: **quien estuviera
 * corrigiendo pierde ese trabajo y no cobra**. Se asume a conciencia, así que
 * conviene que haya una prueba que lo enseñe en vez de descubrirlo en
 * producción.
 */
final class BlockUserTest extends EconomyScenario
{
    public function testBlockingSomebodyAndListingWhoIsBlocked(): void
    {
        $persona = $this->activatedPerson('persona');
        $otra = $this->activatedPerson('otra');

        $this->block($persona['token'], $otra['userId']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->blockedUsers($persona['token']);

        self::assertResponseIsSuccessful();
        self::assertSame([$otra['userId']], $this->ids());
        self::assertNotEmpty($this->payload()['data'][0]['blockedAt']);
    }

    /**
     * `RN-3`: al bloquear se deshacen **las dos** relaciones de seguimiento.
     * Y `RN-7`: desbloquear no las devuelve — hay que volver a seguir.
     */
    public function testBlockingUndoesBothFollowsAndUnblockingDoesNotBringThemBack(): void
    {
        $persona = $this->activatedPerson('persona');
        $otra = $this->activatedPerson('otra');

        $this->follow($persona['token'], $otra['userId']);
        $this->follow($otra['token'], $persona['userId']);

        $this->block($persona['token'], $otra['userId']);
        $this->consumeEverything();

        self::assertFalse($this->follows($persona['token'], $otra['userId']));
        self::assertFalse($this->follows($otra['token'], $persona['userId']));

        $this->unblock($persona['token'], $otra['userId']);
        $this->consumeEverything();

        self::assertFalse($this->follows($persona['token'], $otra['userId']), 'Desbloquear no restaura nada.');
    }

    /**
     * `RN-4`: mientras dure el bloqueo **ninguno** puede seguir al otro, y da
     * igual quién bloqueó a quién.
     *
     * Responde como si esa cuenta no existiera: decir «te ha bloqueado» sería
     * avisar del bloqueo, que es justo lo que `RN-2` evita.
     */
    public function testNeitherCanFollowTheOtherWhileTheBlockLasts(): void
    {
        $persona = $this->activatedPerson('persona');
        $otra = $this->activatedPerson('otra');

        $this->block($persona['token'], $otra['userId']);

        $this->client->request('PUT', \sprintf('/api/v1/users/%s/subscription', $otra['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$persona['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('USER_NOT_FOUND', $this->payload()['code']);

        // Y el bloqueado tampoco, que es la mitad que se olvida.
        $this->client->request('PUT', \sprintf('/api/v1/users/%s/subscription', $persona['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$otra['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Levantado el bloqueo, se puede otra vez.
        $this->unblock($persona['token'], $otra['userId']);
        $this->client->request('PUT', \sprintf('/api/v1/users/%s/subscription', $otra['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$persona['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    /**
     * **`RN-B1`, y lo decide `Reading`.** El bloqueado pierde el acceso a las
     * obras del bloqueador desde ese instante, viniera de donde viniera ese
     * acceso.
     *
     * De ahí sale `RN-B2` sin escribir una regla más: sin acceso no se
     * entrega. Quien llevaba dos horas escribiendo **pierde ese trabajo y no
     * cobra**, porque nunca entregó. Es la única situación del sistema en la
     * que alguien pierde trabajo real por una decisión ajena, y se asume a
     * conciencia.
     */
    public function testBlockingAReaderOfAClosedWorkTakesTheirAccessAndTheirDraftAway(): void
    {
        [$autora, $lectora, $chapterId, $workId] = $this->aRestrictedWorkBeingCorrected();

        // Antes del bloqueo, corrige sin problema.
        $this->panel($chapterId, $lectora['token']);
        self::assertResponseIsSuccessful();

        $this->block($autora['token'], $lectora['userId']);
        $this->consumeEverything();

        $this->panel($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'Ya no puede ni abrir el panel.');

        $this->submit($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, 'Ni entregar lo que llevaba escrito.');

        self::assertSame(0, $this->deliveredBy($lectora['token']), 'Y no cobra: nunca entregó.');
        self::assertNotEmpty($workId);
    }

    /**
     * **`RN-B2` también en una obra pública**, donde no hay acceso que
     * revocar. Lo que cierra la puerta ahí es `User`: un bloqueo vence a
     * cualquier ajuste, y el techo de audiencia responde que no.
     *
     * Son dos caminos distintos para la misma frase de la ficha, y hacen
     * falta los dos: sin el segundo, bloquear no serviría de nada en las
     * obras abiertas, que son la mayoría.
     */
    public function testBlockingAlsoStopsCorrectionsOnAPublicWork(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicWorkBeingCorrected();

        $this->block($autora['token'], $lectora['userId']);
        $this->consumeEverything();

        $this->submit($chapterId, $lectora['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('AUTHOR_DOES_NOT_ACCEPT_COMMENTS', $this->payload()['code']);
    }

    /**
     * Y al revés: **el bloqueador tampoco puede comentar al bloqueado**. El
     * bloqueo es unilateral en la intención y bidireccional en el efecto.
     */
    public function testTheBlockerCannotCorrectTheBlockedPersonsWorkEither(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicWorkBeingCorrected();

        // Bloquea quien corrige, no quien escribe.
        $this->block($lectora['token'], $autora['userId']);
        $this->consumeEverything();

        $this->submit($chapterId, $lectora['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * `RN-B4`: lo ya entregado **no se revierte ni se oculta**. El autor lo
     * pagó y el lector lo ganó. El bloqueo corta el futuro, no reescribe el
     * pasado.
     */
    public function testWhatWasAlreadyDeliveredStays(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicWorkBeingCorrected();

        $this->submit($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame(1, $this->deliveredBy($lectora['token']));

        $this->block($autora['token'], $lectora['userId']);
        $this->consumeEverything();

        self::assertSame(1, $this->deliveredBy($lectora['token']), 'Sigue contando como trabajo hecho.');
    }

    /**
     * Levantar el bloqueo devuelve la palabra —`User` olvida el bloqueo— pero
     * **no devuelve el acceso revocado**: recuperarlo es una decisión del
     * autor, no un efecto secundario.
     */
    public function testUnblockingGivesBackSpeechButNotAccess(): void
    {
        [$autora, $lectora, $chapterId] = $this->aPublicWorkBeingCorrected();

        $this->block($autora['token'], $lectora['userId']);
        $this->consumeEverything();

        $this->unblock($autora['token'], $lectora['userId']);
        $this->consumeEverything();

        $this->submit($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED, 'En una obra pública, vuelve a poder.');
    }

    public function testNobodyBlocksThemselves(): void
    {
        $persona = $this->activatedPerson('persona');

        $this->block($persona['token'], $persona['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('CANNOT_BLOCK_YOURSELF', $this->payload()['code']);
    }

    public function testBlockingSomebodyWhoDoesNotExistIsRefused(): void
    {
        $persona = $this->activatedPerson('persona');

        $this->block($persona['token'], '0192f000-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('USER_NOT_FOUND', $this->payload()['code']);
    }

    public function testBlockingTwiceChangesNothing(): void
    {
        $persona = $this->activatedPerson('persona');
        $otra = $this->activatedPerson('otra');

        $this->block($persona['token'], $otra['userId']);
        $this->block($persona['token'], $otra['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertCount(1, $this->queued('UserBlocked'), 'Un solo hecho.');

        $this->blockedUsers($persona['token']);
        self::assertSame([$otra['userId']], $this->ids(), 'Y una sola fila.');
    }

    public function testUnblockingSomebodyWhoIsNotBlockedIsNotAFailure(): void
    {
        $persona = $this->activatedPerson('persona');
        $otra = $this->activatedPerson('otra');

        $this->unblock($persona['token'], $otra['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame([], $this->announced('UserUnblocked'));
    }

    /**
     * La lista **no se filtra por privacidad**, a diferencia de las de
     * seguidos y seguidores. Si se filtrara, bloquear a alguien que después
     * cierra su perfil lo haría desaparecer de aquí y el bloqueo quedaría sin
     * deshacer para siempre.
     */
    public function testSomebodyWhoClosesTheirProfileStillShowsUpInTheBlockedList(): void
    {
        $persona = $this->activatedPerson('persona');
        $escondida = $this->activatedPerson('escondida');

        $this->block($persona['token'], $escondida['userId']);

        $this->client->request('PUT', '/api/v1/me/privacy-settings', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$escondida['token'],
        ], content: json_encode(['profileVisibility' => 'NOBODY'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->capture();

        $this->blockedUsers($persona['token']);

        self::assertSame([$escondida['userId']], $this->ids(), 'Sigue ahí, o el bloqueo no se podría deshacer.');
    }

    /**
     * `RN-9`: bloquear es escritura.
     */
    public function testAnUnactivatedAccountCannotBlock(): void
    {
        $otra = $this->activatedPerson('otra');
        $token = $this->signedInWithoutActivating('pendiente');

        $this->block($token, $otra['userId']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
    }

    public function testWithoutASessionThereIsNobodyToBlock(): void
    {
        $otra = $this->activatedPerson('otra');

        $this->client->request('PUT', \sprintf('/api/v1/users/%s/block', $otra['userId']));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('GET', '/api/v1/me/blocked-users');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * `RN-10`: se publica el hecho, **con los dos identificadores y nada
     * más**, para que cada contexto decida.
     */
    public function testTheBlockIsAnnouncedSoEachContextCanDecide(): void
    {
        $persona = $this->activatedPerson('persona');
        $otra = $this->activatedPerson('otra');

        $this->block($persona['token'], $otra['userId']);

        $anunciado = $this->lastAnnouncementOf('UserBlocked');
        self::assertSame($persona['userId'], $anunciado['blockerId']);
        self::assertSame($otra['userId'], $anunciado['blockedId']);
        self::assertSame(['blockerId', 'blockedId', 'blockedAt'], array_keys($anunciado));

        $this->unblock($persona['token'], $otra['userId']);

        $levantado = $this->lastAnnouncementOf('UserUnblocked');
        self::assertSame($persona['userId'], $levantado['blockerId']);
    }

    /**
     * Una obra **cerrada a solicitudes** con una lectora dentro y una
     * corrección empezada: el caso donde el bloqueo se lleva por delante trabajo real.
     *
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string, 3: string}
     */
    private function aRestrictedWorkBeingCorrected(): array
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($autora['token']);
        $chapterId = $this->addChapter($workId, $autora['token'], words: 900);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 10],
        ]);
        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        // Empezar concede el acceso de lector beta (`FEAT-RDG-001`).
        $this->start($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->consumeEverything();

        // Y ahora la obra se cierra a solicitudes: lo que la lectora
        // conserva es el acceso que ya tenía.
        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'ON_REQUEST']);
        $this->consumeEverything();

        return [$autora, $lectora, $chapterId, $workId];
    }

    /**
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string}
     */
    private function aPublicWorkBeingCorrected(): array
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($autora['token']);
        $chapterId = $this->addChapter($workId, $autora['token'], words: 900);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 10],
        ]);
        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->put(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        $this->start($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->consumeEverything();

        return [$autora, $lectora, $chapterId];
    }

    private function block(string $token, string $userId): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/users/%s/block', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
    }

    private function unblock(string $token, string $userId): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/users/%s/block', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
    }

    private function blockedUsers(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/blocked-users', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function follow(string $token, string $userId): void
    {
        $this->client->request('PUT', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();
    }

    private function follows(string $token, string $userId): bool
    {
        $this->client->request('GET', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        return (bool) $this->payload()['subscribed'];
    }

    private function panel(string $chapterId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function start(string $chapterId, string $token): void
    {
        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
    }

    private function submit(string $chapterId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $answers = [];

        if ($this->client->getResponse()->isSuccessful()) {
            /** @var list<array{questionId: string}> $questions */
            $questions = $this->payload()['questions'];
            $answers = array_map(
                static fn (array $question): array => [
                    'questionId' => $question['questionId'],
                    'text' => implode(' ', array_fill(0, 60, 'palabra')),
                ],
                $questions,
            );
        }

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['answers' => $answers], \JSON_THROW_ON_ERROR));

        $this->capture();
        $this->consumeEverything();
    }

    private function deliveredBy(string $token): int
    {
        $this->client->request('GET', '/api/v1/me/profile', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        return (int) $this->payload()['counters']['corrections'];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function put(string $path, string $token, array $body): void
    {
        $this->client->request('PUT', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    /**
     * @return list<string>
     */
    private function ids(): array
    {
        /** @var list<array{userId: string}> $data */
        $data = $this->payload()['data'];

        return array_map(static fn (array $person): string => $person['userId'], $data);
    }
}
