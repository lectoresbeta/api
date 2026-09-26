<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Notification;

use Symfony\Component\HttpFoundation\Response;

/**
 * Convertir hechos en avisos (`FEAT-NOT-001`).
 *
 * **Salda la deuda de dos fichas**: hasta ahora, quien perdía el acceso a una
 * obra que estaba corrigiendo descubría que su texto ya no se podía entregar,
 * sin explicación y sin aviso.
 *
 * Nada se fabrica a mano aquí: cada aviso nace de una llamada real a la API
 * del contexto de origen, y el hecho viaja codificado y decodificado por el
 * serializador de verdad.
 */
final class InAppNotificationsTest extends NotificationScenario
{
    public function testAskingForAccessNotifiesTheAuthorAndNotTheAsker(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($autora);

        $this->ask($workId, $lectora['token']);
        $this->consumeEverything();

        self::assertSame(['ACCESS_REQUESTED'], $this->kinds($autora['token']));
        self::assertSame([], $this->kinds($lectora['token']), 'Quien pregunta ya sabe que ha preguntado.');
    }

    /**
     * **Por qué el aviso guarda nombres.** Sin ellos, pintar una bandeja de
     * veinte filas serían cuarenta peticiones más a otros contextos.
     */
    public function testTheNoticeCarriesWhoCausedItAndWhichWork(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($autora, 'La ciudad de los pájaros');

        $this->ask($workId, $lectora['token']);
        $this->consumeEverything();

        $payload = $this->notice($autora['token'], 'ACCESS_REQUESTED')['payload'];

        self::assertSame($lectora['userId'], $payload['actorId']);
        self::assertSame($workId, $payload['workId']);
        self::assertSame('La ciudad de los pájaros', $payload['workTitle']);
        self::assertArrayHasKey('actorUsername', $payload);
    }

    /**
     * `RN-4`: ni una línea del texto. Es la misma regla que impide que una
     * obra inédita salga por correo, aplicada al canal que sí se queda
     * dentro — porque la regla es del aviso, no del canal.
     */
    public function testTheNoticeCarriesNoContent(): void
    {
        [$autora, $lectora, $chapterId] = $this->aCorrectionAboutToBeDelivered();

        $this->submit($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->consumeEverything();

        $payload = $this->notice($autora['token'], 'CORRECTION_RECEIVED')['payload'];

        self::assertArrayHasKey('correctionId', $payload, 'El enlace, sí.');
        self::assertArrayHasKey('chapterId', $payload);

        $written = implode(' ', array_map(strval(...), array_values((array) $payload)));
        self::assertStringNotContainsString('palabra', $written, 'El texto de la corrección, no.');
    }

    public function testResolvingARequestNotifiesTheReaderBothWays(): void
    {
        $autora = $this->activatedPerson('autora');
        $aceptada = $this->activatedPerson('aceptada');
        $rechazada = $this->activatedPerson('rechazada');
        $workId = $this->onRequestWork($autora);

        $this->resolveRequest($this->ask($workId, $aceptada['token']), $autora['token'], 'ACCEPTED');
        $this->resolveRequest($this->ask($workId, $rechazada['token']), $autora['token'], 'REJECTED');
        $this->consumeEverything();

        self::assertSame('GRANTED', $this->notice($aceptada['token'], 'ACCESS_REQUEST_RESOLVED')['payload']['outcome']);

        $no = $this->notice($rechazada['token'], 'ACCESS_REQUEST_RESOLVED')['payload'];
        self::assertSame('REJECTED', $no['outcome']);
        self::assertSame($workId, $no['workId'], 'Sobre qué obra, porque la bandeja enseña varios juntos.');
        self::assertArrayNotHasKey('actorId', $no, 'Quien rechaza no tiene por qué dar la cara.');
    }

    /**
     * `RN-3` con el caso que de verdad cuesta: **el mismo hecho, tres
     * caminos**. Empezar a corregir una obra pública concede acceso y publica
     * `BetaReaderAccessGranted`, igual que aprobar una solicitud. Avisar ahí
     * sería contarle a alguien lo que acaba de hacer.
     */
    public function testAnAccessTheReaderStartedDoesNotNotifyThem(): void
    {
        [, $lectora] = $this->aCorrectionAboutToBeDelivered();

        self::assertNotContains('ACCESS_REQUEST_RESOLVED', $this->kinds($lectora['token']));
    }

    /**
     * Lo mismo por el otro camino: aceptar una invitación es cosa de quien la
     * acepta. Lo que sí recibe es la invitación.
     */
    public function testAcceptingAnInvitationDoesNotNotifyTheOneWhoAccepted(): void
    {
        $autora = $this->activatedPerson('autora');
        $invitada = $this->activatedPerson('invitada');
        $workId = $this->onRequestWork($autora);
        $this->accessMode($workId, $autora['token'], 'PRIVATE');

        $invitationId = $this->invite($workId, $autora['token'], $invitada['userId']);
        $this->consumeEverything();

        self::assertSame(['BETA_READER_INVITATION'], $this->kinds($invitada['token']));

        $this->resolveInvitation($invitationId, $invitada['token'], 'ACCEPTED');
        $this->consumeEverything();

        self::assertSame(['BETA_READER_INVITATION'], $this->kinds($invitada['token']), 'Y nada más.');
    }

    /**
     * **El aviso que saldó la deuda.** Quien pierde el acceso deja de poder
     * entregar lo que estaba escribiendo, y hasta ahora nada se lo decía.
     *
     * No dice por qué: el hecho no distingue los caminos, y contar que ha
     * habido un bloqueo sería avisar de un bloqueo, que es justo lo que
     * `FEAT-COM-034` `RN-2` evita.
     */
    public function testLosingAccessNotifiesWhoLosesIt(): void
    {
        [$autora, $lectora, $workId] = $this->aReaderInsideAClosedWork();

        $this->revoke($workId, $lectora['userId'], $autora['token']);
        $this->consumeEverything();

        $aviso = $this->notice($lectora['token'], 'BETA_READER_ACCESS_REVOKED');

        self::assertSame($workId, $aviso['payload']['workId']);
        self::assertArrayNotHasKey('reason', $aviso['payload']);
    }

    /**
     * Y por el otro camino, el que `FEAT-COM-034` dejó anotado: un bloqueo
     * retira el acceso, y quien lo pierde se entera por el mismo sitio.
     */
    public function testBeingBlockedAlsoNotifiesTheReader(): void
    {
        [$autora, $lectora, $workId] = $this->aReaderInsideAClosedWork();

        $this->client->request('PUT', \sprintf('/api/v1/users/%s/block', $lectora['userId']), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();
        $this->consumeEverything();

        self::assertSame($workId, $this->notice($lectora['token'], 'BETA_READER_ACCESS_REVOKED')['payload']['workId']);
    }

    public function testDeliveringACorrectionNotifiesTheAuthor(): void
    {
        [$autora, $lectora, $chapterId, $workId] = $this->aCorrectionAboutToBeDelivered();

        $this->submit($chapterId, $lectora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->consumeEverything();

        $payload = $this->notice($autora['token'], 'CORRECTION_RECEIVED')['payload'];

        self::assertSame($lectora['userId'], $payload['actorId']);
        self::assertSame($workId, $payload['workId']);
    }

    /**
     * `RN-2`: RabbitMQ no promete entrega única, así que reentregar es
     * normal. Lo que no puede pasar es que la campana marque dos.
     */
    public function testTheSameFactDeliveredTwiceDoesNotCreateTwoNotices(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($autora);

        $this->ask($workId, $lectora['token']);
        $this->consumeEverything();
        $this->consumeEverything();
        $this->deliver($this->queued('AccessRequested'));

        self::assertSame(['ACCESS_REQUESTED'], $this->kinds($autora['token']));
        self::assertSame(1, $this->unreadCount($autora['token']));
    }

    /**
     * Una obra cerrada con una lectora dentro, que entró porque su solicitud
     * fue aprobada.
     *
     * Este camino y no el de una obra pública, porque en una pública el
     * acceso **se recupera solo** en cuanto se empieza otra corrección
     * (`FEAT-RDG-010` `RN-7`), y lo que se prueba aquí es perderlo.
     *
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string}
     */
    private function aReaderInsideAClosedWork(): array
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');
        $workId = $this->onRequestWork($autora);

        $this->resolveRequest($this->ask($workId, $lectora['token']), $autora['token'], 'ACCEPTED');
        $this->consumeEverything();

        return [$autora, $lectora, $workId];
    }

    /**
     * Una obra pública con una corrección empezada: el camino por el que
     * alguien entra sin pedir permiso, y el que hace falta para probar todo
     * lo que pasa después.
     *
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string, 3: string}
     */
    private function aCorrectionAboutToBeDelivered(): array
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->onRequestWork($autora);
        $chapterId = $this->chapterOf($workId, $autora['token']);
        $this->put(\sprintf('/api/v1/works/%s/questionnaire', $workId), $autora['token'], [
            'questions' => [['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 10]],
        ]);
        $this->accessMode($workId, $autora['token'], 'PUBLIC');
        $this->changeStatus($workId, $autora['token'], 'IN_CORRECTION');
        $this->consumeEverything();

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();

        return [$autora, $lectora, $chapterId, $workId];
    }

    private function chapterOf(string $workId, string $token): string
    {
        $this->client->request('GET', \sprintf('/api/v1/works/%s', $workId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();

        /** @var list<array{chapterId: string}> $chapters */
        $chapters = $this->payload()['chapters'];

        return $chapters[0]['chapterId'];
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
        ], content: json_encode([
            'answers' => array_map(
                static fn (array $question): array => [
                    'questionId' => $question['questionId'],
                    'text' => implode(' ', array_fill(0, 60, 'palabra')),
                ],
                $questions,
            ),
        ], \JSON_THROW_ON_ERROR));

        $this->capture();
    }
}
