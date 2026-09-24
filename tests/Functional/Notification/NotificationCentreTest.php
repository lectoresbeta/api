<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Notification;

use Symfony\Component\HttpFoundation\Response;

/**
 * La bandeja y el contador (`FEAT-NOT-009`).
 *
 * Es la otra mitad de `FEAT-NOT-001`: sin esto, los avisos se guardan y nadie
 * los ve.
 */
final class NotificationCentreTest extends NotificationScenario
{
    public function testTheInboxShowsMyNoticesNewestFirst(): void
    {
        [$autora, $primera, $segunda] = $this->anAuthorWithTwoRequests();

        self::assertSame(['ACCESS_REQUESTED', 'ACCESS_REQUESTED'], $this->kinds($autora['token']));

        /** @var list<array{payload: array{actorId: string}}> $data */
        $data = $this->payload()['data'];

        self::assertSame(
            [$segunda['userId'], $primera['userId']],
            [$data[0]['payload']['actorId'], $data[1]['payload']['actorId']],
            'Lo último primero.',
        );
    }

    public function testTheCounterSaysHowManyAreUnread(): void
    {
        [$autora] = $this->anAuthorWithTwoRequests();

        self::assertSame(2, $this->unreadCount($autora['token']));
    }

    public function testMarkingOneReadTakesItOutOfTheCounter(): void
    {
        [$autora] = $this->anAuthorWithTwoRequests();

        $this->markRead($this->notice($autora['token'], 'ACCESS_REQUESTED')['notificationId'], $autora['token']);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(1, $this->unreadCount($autora['token']));
    }

    /**
     * `RN-4`: la pantalla marca al abrir **y además** ofrece el gesto, así que
     * la segunda llamada llega sola. No puede ser un error, y no puede mover
     * la fecha: un aviso se leyó cuando se leyó.
     */
    public function testMarkingSomethingAlreadyReadChangesNothing(): void
    {
        [$autora] = $this->anAuthorWithTwoRequests();
        $notificationId = $this->notice($autora['token'], 'ACCESS_REQUESTED')['notificationId'];

        $this->markRead($notificationId, $autora['token']);
        $leidoA = $this->notice($autora['token'], 'ACCESS_REQUESTED')['readAt'];

        $this->markRead($notificationId, $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertSame($leidoA, $this->notice($autora['token'], 'ACCESS_REQUESTED')['readAt']);
    }

    /**
     * `RN-2`: lo que la pantalla enseña por defecto. El endpoint, en cambio,
     * devuelve la bandeja entera si no se le pide otra cosa.
     */
    public function testTheInboxCanBeAskedForWhatIsUnreadOnly(): void
    {
        [$autora] = $this->anAuthorWithTwoRequests();

        $this->markRead($this->notice($autora['token'], 'ACCESS_REQUESTED')['notificationId'], $autora['token']);

        self::assertCount(2, $this->kinds($autora['token']));
        self::assertCount(1, $this->kinds($autora['token'], ['unreadOnly' => 'true']));
    }

    /**
     * `RN-5`: **un contador que no se puede vaciar acaba ignorado.** Y vaciar
     * lo ya vacío tampoco falla: sería poner una pega a lo único que lo
     * vacía.
     */
    public function testMarkingEverythingEmptiesTheCounter(): void
    {
        [$autora] = $this->anAuthorWithTwoRequests();

        $this->markAllRead($autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(0, $this->unreadCount($autora['token']));

        $this->markAllRead($autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    /**
     * `RN-1`: sin repetir ni saltarse nada. La ordenación es por fecha **y**
     * identificador, que es lo que evita que dos avisos del mismo instante se
     * pisen entre páginas.
     */
    public function testTheInboxPaginatesByCursor(): void
    {
        [$autora] = $this->anAuthorWithTwoRequests();

        $this->inbox($autora['token'], ['limit' => 1]);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->payload()['pageInfo']['hasNextPage']);

        $primera = $this->payload()['data'][0]['notificationId'];
        $cursor = (string) $this->payload()['pageInfo']['nextCursor'];

        $this->inbox($autora['token'], ['limit' => 1, 'cursor' => $cursor]);
        self::assertResponseIsSuccessful();
        self::assertNotSame($primera, $this->payload()['data'][0]['notificationId']);
        self::assertFalse($this->payload()['pageInfo']['hasNextPage']);
    }

    public function testABrokenCursorIsRefusedInsteadOfServingTheFirstPage(): void
    {
        [$autora] = $this->anAuthorWithTwoRequests();

        $this->inbox($autora['token'], ['cursor' => 'lo-que-sea']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('INVALID_CURSOR', $this->payload()['code']);
    }

    /**
     * `RN-6`: un aviso ajeno responde como si no existiera. Nunca `403` —
     * confirmaría que está ahí, y con identificadores al azar se podría ir
     * descubriendo lo que le pasa a otra persona.
     */
    public function testSomebodyElsesNoticeAnswersAsIfItDidNotExist(): void
    {
        [$autora] = $this->anAuthorWithTwoRequests();
        $extrana = $this->activatedPerson('extrana');

        $notificationId = $this->notice($autora['token'], 'ACCESS_REQUESTED')['notificationId'];

        self::assertSame([], $this->kinds($extrana['token']), 'Ni se le enseña…');

        $this->markRead($notificationId, $extrana['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('NOTIFICATION_NOT_FOUND', $this->payload()['code'], '…ni se le deja tocarlo.');

        self::assertSame(2, $this->unreadCount($autora['token']), 'Y sigue sin leer.');
    }

    public function testANoticeThatDoesNotExistAnswersTheSame(): void
    {
        [$autora] = $this->anAuthorWithTwoRequests();

        $this->markRead('11111111-1111-4111-8111-111111111111', $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->markRead('no-es-un-identificador', $autora['token']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('NOTIFICATION_NOT_FOUND', $this->payload()['code']);
    }

    /**
     * La bandeja es de los primeros sitios donde alguien sin activar necesita
     * entrar: **ahí está el aviso de que active**.
     */
    public function testAnUnactivatedAccountCanReadItsInbox(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        self::assertSame(0, $this->unreadCount($token));
        self::assertSame([], $this->kinds($token));

        $this->markAllRead($token);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testWithoutASessionThereIsNoInbox(): void
    {
        $this->client->request('GET', '/api/v1/me/notifications');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('GET', '/api/v1/me/notifications/unread-count');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('PUT', '/api/v1/me/notifications/read');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('PUT', '/api/v1/me/notifications/11111111-1111-4111-8111-111111111111/read');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Dos avisos reales en la bandeja de una misma persona, provocados por
     * dos hechos distintos y en orden conocido.
     *
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: array{token: string, userId: string}}
     */
    private function anAuthorWithTwoRequests(): array
    {
        $autora = $this->activatedPerson('autora');
        $primera = $this->activatedPerson('primera');
        $segunda = $this->activatedPerson('segunda');
        $workId = $this->onRequestWork($autora);

        $this->ask($workId, $primera['token']);
        $this->consumeEverything();

        $this->ask($workId, $segunda['token']);
        $this->consumeEverything();

        return [$autora, $primera, $segunda];
    }
}
