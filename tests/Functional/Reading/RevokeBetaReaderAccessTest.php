<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Reading;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retirar el acceso de un lector beta (`FEAT-RDG-010`).
 *
 * Cierra `R-1`, abierta desde la primera ficha de este contexto: **se entra a
 * una obra por tres caminos y hasta ahora no había forma de salir** salvo
 * bloquear a la persona, que es una respuesta social a un problema que muchas
 * veces no lo es.
 *
 * Dos cosas se prueban aquí con especial cuidado, porque son las que alguien
 * esperará distintas:
 *
 * - **revocar no expulsa de una obra pública**: esa persona vuelve a entrar
 *   en cuanto empiece otra corrección, porque así funciona `PUBLIC`;
 * - **un acceso ganado también se revoca**. Lo que el lector ganó —cobrar y
 *   que su corrección cuente— no se le quita; lo que se retira es la lectura
 *   futura de un texto ajeno.
 */
final class RevokeBetaReaderAccessTest extends EconomyScenario
{
    public function testTheAuthorSeesWhoCanReadTheirWork(): void
    {
        [$autora, $lectora, , $workId] = $this->aClosedWorkWithAReaderInside();

        $this->betaReaders($workId, $autora['token']);

        self::assertResponseIsSuccessful();
        self::assertSame([$lectora['userId']], $this->ids());
        self::assertSame('PUBLIC_JOIN', $this->payload()['data'][0]['source'], 'Y por dónde entró.');
        self::assertNotEmpty($this->payload()['data'][0]['grantedAt']);
    }

    /**
     * Quién está leyendo una obra inédita es asunto de su autor. Una obra
     * ajena responde `404` y no `403`: un `403` confirmaría que existe.
     */
    public function testNobodyElseSeesThatList(): void
    {
        [, $lectora, , $workId] = $this->aClosedWorkWithAReaderInside();

        $this->betaReaders($workId, $lectora['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * **Lo que cierra el hueco de `FEAT-RDG-001`**: quien empezó a corregir y
     * cerró la pestaña conservaba el acceso para siempre, porque cerrar una
     * pestaña no es un hecho que nadie publique. Ahora el autor lo resuelve.
     */
    public function testRevokingTakesTheAccessAwayAtOnce(): void
    {
        [$autora, $lectora, $chapterId, $workId] = $this->aClosedWorkWithAReaderInside();

        $this->panel($chapterId, $lectora['token']);
        self::assertResponseIsSuccessful('Antes, entraba.');

        $this->revoke($workId, $lectora['userId'], $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->panel($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->betaReaders($workId, $autora['token']);
        self::assertSame([], $this->ids(), 'Y desaparece de la lista.');
    }

    /**
     * `RN-4`: una corrección en curso **deja de poder entregarse**, igual que
     * con un bloqueo. Quien la estaba escribiendo no cobra, porque nunca
     * entregó, y su borrador se conserva porque es texto suyo.
     */
    public function testACorrectionInProgressCanNoLongerBeDelivered(): void
    {
        [$autora, $lectora, $chapterId, $workId] = $this->aClosedWorkWithAReaderInside();

        $this->revoke($workId, $lectora['userId'], $autora['token']);

        $this->submit($chapterId, $lectora['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(0, $this->deliveredBy($lectora['token']), 'Y no cobra: nunca entregó.');
    }

    /**
     * `RN-5`: lo ya entregado **no se toca**. El autor lo pagó y el lector lo
     * ganó. Revocar corta el futuro, no reescribe el pasado.
     *
     * `RN-6`: y aun así, ese acceso ganado se puede retirar. Es la asimetría
     * incómoda de esta funcionalidad, y va probada en vez de disimulada.
     */
    public function testAnEarnedAccessCanBeRevokedAndWhatWasDeliveredStays(): void
    {
        [$autora, $lectora, $chapterId, $workId] = $this->aPublicWorkBeingCorrected();

        $this->submit($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame(1, $this->deliveredBy($lectora['token']));

        $this->betaReaders($workId, $autora['token']);
        self::assertTrue($this->payload()['data'][0]['earned'], 'Se lo ganó entregando.');

        $this->revoke($workId, $lectora['userId'], $autora['token']);

        $this->betaReaders($workId, $autora['token']);
        self::assertSame([], $this->ids(), 'Y aun así se le puede retirar.');

        self::assertSame(1, $this->deliveredBy($lectora['token']), 'Lo entregado sigue contando.');
    }

    /**
     * **`RN-7`, la parte que se malinterpreta**: en una obra `PUBLIC`,
     * revocar corta lo que está pasando ahora y no deja a nadie fuera. Esa
     * persona vuelve a tener acceso en cuanto empiece otra corrección, porque
     * así funciona esa modalidad.
     *
     * Para dejar a alguien fuera hay que cerrar la obra o bloquearle.
     */
    public function testOnAPublicWorkRevokingDoesNotKeepAnybodyOut(): void
    {
        [$autora, $lectora, $chapterId, $workId] = $this->aPublicWorkBeingCorrected();

        $this->revoke($workId, $lectora['userId'], $autora['token']);

        $this->betaReaders($workId, $autora['token']);
        self::assertSame([], $this->ids());

        // Vuelve a empezar, y vuelve a entrar.
        $this->start($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->consumeEverything();

        $this->betaReaders($workId, $autora['token']);
        self::assertSame([$lectora['userId']], $this->ids(), 'Así funciona PUBLIC, y conviene saberlo.');
    }

    /**
     * `RN-2`: revocar a quien no tiene acceso no es un error. El estado que
     * se pedía ya se cumple, y quien lo repite suele ser un reintento.
     */
    public function testRevokingSomebodyWithoutAccessIsNotAFailure(): void
    {
        [$autora, , , $workId] = $this->aClosedWorkWithAReaderInside();
        $extrana = $this->activatedPerson('extrana');

        $this->revoke($workId, $extrana['userId'], $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Y repetirlo sobre quien sí tenía, tampoco.
        $this->revoke($workId, $extrana['userId'], $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame([], $this->announced('BetaReaderAccessRevoked'), 'Sin acceso que retirar, no se anuncia nada.');
    }

    public function testNobodyElseCanRevokeAccessToSomebodyElsesWork(): void
    {
        [, $lectora, , $workId] = $this->aClosedWorkWithAReaderInside();
        $extrana = $this->activatedPerson('extrana');

        $this->revoke($workId, $lectora['userId'], $extrana['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('WORK_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * `RN-10`: el mismo hecho que publican los otros dos caminos de
     * revocación —descartar un borrador y un bloqueo—. Quien lo consume no
     * tiene por qué saber cuál fue.
     */
    public function testRevokingIsAnnounced(): void
    {
        [$autora, $lectora, , $workId] = $this->aClosedWorkWithAReaderInside();

        $this->revoke($workId, $lectora['userId'], $autora['token']);

        $anunciado = $this->lastAnnouncementOf('BetaReaderAccessRevoked');

        self::assertSame($workId, $anunciado['workId']);
        self::assertSame($lectora['userId'], $anunciado['readerId']);
    }

    public function testAnUnactivatedAccountCannotRevoke(): void
    {
        [, $lectora, , $workId] = $this->aClosedWorkWithAReaderInside();
        $token = $this->signedInWithoutActivating('pendiente');

        $this->revoke($workId, $lectora['userId'], $token);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
    }

    public function testWithoutASessionThereIsNothingToSeeOrRevoke(): void
    {
        [, $lectora, , $workId] = $this->aClosedWorkWithAReaderInside();

        $this->client->request('GET', \sprintf('/api/v1/works/%s/beta-readers', $workId));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('DELETE', \sprintf('/api/v1/works/%s/beta-readers/%s', $workId, $lectora['userId']));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Una obra **ya cerrada a solicitudes** con una lectora dentro: entró
     * cuando era pública, empezando a corregir, y conserva el acceso.
     *
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string, 3: string}
     */
    private function aClosedWorkWithAReaderInside(): array
    {
        [$autora, $lectora, $chapterId, $workId] = $this->aPublicWorkBeingCorrected();

        $this->put(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'ON_REQUEST']);
        $this->consumeEverything();

        return [$autora, $lectora, $chapterId, $workId];
    }

    /**
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string, 3: string}
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

        return [$autora, $lectora, $chapterId, $workId];
    }

    private function betaReaders(string $workId, string $token): void
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s/beta-readers', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function revoke(string $workId, string $readerId, string $token): void
    {
        $this->client->request('DELETE', \sprintf('/api/v1/works/%s/beta-readers/%s', $workId, $readerId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $this->capture();
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
        $this->panel($chapterId, $token);

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

        return array_map(static fn (array $reader): string => $reader['userId'], $data);
    }
}
