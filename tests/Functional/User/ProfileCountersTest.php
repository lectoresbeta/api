<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Community\Subscription\Application\Contract\SubscriptionCount;
use LectoresBeta\Community\Subscription\Application\Contract\SubscriptionCounts;
use LectoresBeta\Community\Subscription\Application\Service\CountSubscriptions;
use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los contadores de la cabecera del perfil (`FEAT-USR-028`).
 *
 * **Las cuatro cifras son de otros tres contextos** —`Community`, `Work` y
 * `Feedback`—, y eso es lo que esta prueba defiende de verdad: que salgan
 * bien sin que `User` mire una sola tabla ajena, y que la pantalla siga
 * abriéndose cuando alguna no se pueda saber.
 *
 * Un contador en `null` significa «no se ha podido saber», que **no es lo
 * mismo que cero**. Un cliente que los trate igual enseñará un 0 donde
 * debería enseñar un hueco.
 */
final class ProfileCountersTest extends EconomyScenario
{
    public function testANewAccountHasFourZeroes(): void
    {
        $person = $this->activatedPerson('persona');

        $this->myProfile($person['token']);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['following' => 0, 'followers' => 0, 'works' => 0, 'corrections' => 0],
            $this->payload()['counters'],
            'Cero, no nulo: se sabe, y es cero.',
        );
    }

    /**
     * Seguidos y seguidores salen de `Community`, y son **asimétricos**:
     * seguir a alguien sube mi contador de seguidos y el suyo de seguidores,
     * no al revés.
     */
    public function testFollowingMovesTwoCountersInDifferentProfiles(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');

        $this->follow($lectora['token'], $autora['userId']);

        $this->myProfile($lectora['token']);
        self::assertSame(1, $this->payload()['counters']['following']);
        self::assertSame(0, $this->payload()['counters']['followers']);

        $this->myProfile($autora['token']);
        self::assertSame(0, $this->payload()['counters']['following']);
        self::assertSame(1, $this->payload()['counters']['followers']);
    }

    public function testUnfollowingBringsThemBackDown(): void
    {
        $lectora = $this->activatedPerson('lectora');
        $autora = $this->activatedPerson('autora');

        $this->follow($lectora['token'], $autora['userId']);
        $this->unfollow($lectora['token'], $autora['userId']);

        $this->myProfile($lectora['token']);
        self::assertSame(0, $this->payload()['counters']['following']);

        $this->myProfile($autora['token']);
        self::assertSame(0, $this->payload()['counters']['followers']);
    }

    /**
     * **El contador cuenta lo que hay, sin filtrar por privacidad**, y eso
     * tiene una consecuencia que conviene conocer: si quien te sigue tiene el
     * perfil cerrado, tu contador dice uno y tu lista de seguidores viene
     * vacía (`FEAT-COM-027` `RN-3`).
     *
     * Filtrar la cifra costaría resolver la visibilidad de cada seguidor para
     * pintar un número y, peor, la haría distinta para cada visitante: el
     * contador dejaría de ser un dato de esa persona para ser uno de quien
     * mira.
     */
    public function testTheCounterCountsEverybodyEvenWhoIsHidden(): void
    {
        $autora = $this->activatedPerson('autora');
        $escondida = $this->activatedPerson('escondida');

        $this->follow($escondida['token'], $autora['userId']);
        $this->restrict($escondida['token'], 'NOBODY');

        $this->myProfile($autora['token']);
        self::assertSame(1, $this->payload()['counters']['followers']);

        $this->client->request('GET', \sprintf('/api/v1/users/%s/subscribers', $autora['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertSame([], $this->payload()['data'], 'Y la lista, vacía. No es una contradicción: es la privacidad de quien sigue.');
    }

    /**
     * «Relatos» sale de `Work` y cuenta **todas** las obras propias, sea cual
     * sea su estado: en su propio perfil, sus borradores son suyos. El día
     * que este contador salga en el perfil ajeno hará falta otra pregunta.
     */
    public function testWorksCountsEveryOwnWorkWhateverItsStatus(): void
    {
        $autora = $this->activatedPerson('autora');

        $this->createWork($autora['token'], 'Un borrador');
        $this->createWork($autora['token'], 'Otro borrador');

        $this->myProfile($autora['token']);

        self::assertSame(2, $this->payload()['counters']['works']);
    }

    /**
     * Y no cuenta las de los demás, que es la otra mitad de la afirmación.
     */
    public function testSomebodyElsesWorksDoNotCount(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->createWork($autora['token'], 'La ciudad de los pájaros');

        $this->myProfile($lectora['token']);

        self::assertSame(0, $this->payload()['counters']['works']);
    }

    /**
     * «Correcciones» sale de `Feedback` y cuenta las **entregadas**: una
     * empezada y sin enviar no es trabajo hecho, y contarla haría un contador
     * que sube y baja solo.
     */
    public function testCorrectionsCountsOnlyWhatWasDelivered(): void
    {
        [$autora, $lectora, $chapterId] = $this->aWorkOpenForCorrection();

        $this->start($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->myProfile($lectora['token']);
        self::assertSame(0, $this->payload()['counters']['corrections'], 'Empezada no es entregada.');

        $this->submit($chapterId, $lectora['token']);

        $this->myProfile($lectora['token']);
        self::assertSame(1, $this->payload()['counters']['corrections']);

        $this->myProfile($autora['token']);
        self::assertSame(0, $this->payload()['counters']['corrections'], 'Quien la recibe no la ha dado.');
    }

    /**
     * `RN-3`, la regla que hace que la cabecera no dependa de que los cuatro
     * contextos estén sanos a la vez: **una cifra que no se puede saber viaja
     * como `null` y el perfil se pinta igual**.
     *
     * El fallo se provoca sustituyendo el contrato por uno que revienta, que
     * es lo más parecido a que ese contexto no esté. Lo que se comprueba no
     * es el fallo sino lo que pasa con el resto: que sigue ahí, y que `null`
     * y cero no se confunden.
     */
    public function testACounterThatCannotBeReadDoesNotBringDownTheProfile(): void
    {
        $person = $this->activatedPerson('persona');
        $this->createWork($person['token'], 'La ciudad de los pájaros');

        $this->breakSubscriptionCounts();

        $this->myProfile($person['token']);

        self::assertResponseIsSuccessful('El perfil se abre igual.');
        self::assertNull($this->payload()['counters']['following'], 'No se ha podido saber.');
        self::assertNull($this->payload()['counters']['followers']);
        self::assertSame(1, $this->payload()['counters']['works'], 'Y los que sí se saben, se dicen.');
        self::assertSame(0, $this->payload()['counters']['corrections']);
    }

    /**
     * Ver el perfil propio funciona **sin activar la cuenta** (`RN-10`): es
     * solo lectura, y la pantalla tiene que poder abrirse para enseñar el
     * botón de activar. Los contadores no cambian eso.
     */
    public function testAnUnactivatedAccountSeesItsOwnCounters(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        $this->myProfile($token);

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->payload()['counters']['works']);
    }

    /**
     * Guardar el perfil devuelve **la misma forma** que leerlo, contadores
     * incluidos: quien acaba de guardar no tiene por qué recargar para volver
     * a tener lo que ya tenía.
     */
    public function testSavingReturnsTheSameShapeAsReading(): void
    {
        $person = $this->activatedPerson('persona');
        $this->createWork($person['token'], 'La ciudad de los pájaros');

        $this->client->request('PATCH', '/api/v1/me/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['name' => 'Ana García'], \JSON_THROW_ON_ERROR));
        $this->capture();

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->payload()['counters']['works']);
    }

    private function myProfile(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/profile', server: [
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

    private function unfollow(string $token, string $userId): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/users/%s/subscription', $userId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
    }

    private function restrict(string $token, string $audience): void
    {
        $this->client->request('PUT', '/api/v1/me/privacy-settings', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['profileVisibility' => $audience], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
    }

    /**
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string}
     */
    private function aWorkOpenForCorrection(): array
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

        return [$autora, $lectora, $chapterId];
    }

    private function start(string $chapterId, string $token): void
    {
        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
        $this->consumeEverything();
    }

    private function submit(string $chapterId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{questionId: string}> $questions */
        $questions = $this->payload()['questions'];

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['answers' => array_map(
            static fn (array $question): array => [
                'questionId' => $question['questionId'],
                'text' => implode(' ', array_fill(0, 60, 'palabra')),
            ],
            $questions,
        )], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->capture();
        $this->consumeEverything();
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
     * El contexto que responde por los seguidores, roto.
     *
     * Se sustituye el servicio en vez de tirar su tabla, y no es un atajo:
     * romper la base de datos dentro de la transacción del test envenenaría
     * también las consultas de los otros tres contadores, que es justo lo que
     * esta prueba quiere ver sobrevivir.
     */
    private function breakSubscriptionCounts(): void
    {
        self::getContainer()->set(CountSubscriptions::class, new class implements SubscriptionCounts {
            public function of(string $userId): SubscriptionCount
            {
                throw new \RuntimeException('Community is not answering.');
            }
        });
    }
}
