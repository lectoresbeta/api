<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * Qué propuestas admite cada persona (`FEAT-USR-011`).
 *
 * **No es una preferencia de aviso sino de recepción** (`S-19`), y la
 * diferencia es la prueba que más importa aquí: silenciar una notificación
 * dejaría la invitación esperando respuesta en algún sitio; cerrar esto
 * impide que llegue a existir.
 *
 * Dos interruptores y no uno, porque son dos peticiones distintas: leer un
 * texto ajeno es un rato y ser compañero de escritura es un compromiso.
 */
final class ReceptionSettingsTest extends EconomyScenario
{
    /**
     * `RN-1`: los dos empiezan abiertos, y quien nunca los ha tocado recibe
     * los valores por defecto y no un hueco.
     */
    public function testBothStartOpen(): void
    {
        $persona = $this->activatedPerson('persona');

        $ajustes = $this->settingsOf($persona['token']);

        self::assertTrue($ajustes['betaReaderInvitations']);
        self::assertTrue($ajustes['writingBuddyProposals']);
    }

    /**
     * `RN-2`: **mover un interruptor no pisa el otro.** Exigir los dos en
     * cada petición haría que mover uno sobrescribiera el otro con lo que el
     * cliente tuviera cargado, que es como se pierden ajustes sin que nadie
     * lo note.
     */
    public function testMovingOneSwitchLeavesTheOtherAlone(): void
    {
        $persona = $this->activatedPerson('persona');

        $ajustes = $this->change($persona['token'], ['betaReaderInvitations' => false]);
        self::assertFalse($ajustes['betaReaderInvitations']);
        self::assertTrue($ajustes['writingBuddyProposals'], 'El otro no se ha movido.');

        $ajustes = $this->change($persona['token'], ['writingBuddyProposals' => false]);
        self::assertFalse($ajustes['betaReaderInvitations'], 'Y el primero sigue como estaba.');
        self::assertFalse($ajustes['writingBuddyProposals']);
    }

    /**
     * **La prueba que da sentido a la ficha**: cerrarlo impide que la
     * invitación exista, no solo que se avise de ella.
     */
    public function testClosingTheDoorStopsTheInvitationFromBeingCreated(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $this->change($lectora['token'], ['betaReaderInvitations' => false]);
        $this->consumeEverything();

        $workId = $this->createWork($autora['token'], 'La obra que nadie leerá');
        $this->addChapter($workId, $autora['token'], words: 900);

        $this->client->request('POST', \sprintf('/api/v1/works/%s/beta-reader-invitations', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['userId' => $lectora['userId']], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('INVITATIONS_NOT_ACCEPTED', $this->payload()['code']);
        $this->capture();
        $this->consumeEverything();

        // Y no queda nada esperando en ningún sitio.
        $this->client->request('GET', '/api/v1/me/beta-reader-invitations', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->payload()['invitations'], 'No existe, no es que esté silenciada.');
    }

    /**
     * Y con el buzón abierto la invitación entra, que es la otra mitad de lo
     * mismo.
     */
    public function testWithTheDoorOpenTheInvitationGoesThrough(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($autora['token'], 'La obra que sí');
        $this->addChapter($workId, $autora['token'], words: 900);

        $this->client->request('POST', \sprintf('/api/v1/works/%s/beta-reader-invitations', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['userId' => $lectora['userId']], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /**
     * `RN-3`: cerrar una puerta **no deshace lo que ya llegó**. Una
     * invitación pendiente sigue esperando respuesta — el ajuste dice quién
     * puede proponer a partir de ahora, no borra lo que alguien propuso de
     * buena fe.
     */
    public function testClosingItLeavesPendingInvitationsAlone(): void
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($autora['token'], 'La obra de antes');
        $this->addChapter($workId, $autora['token'], words: 900);

        $this->client->request('POST', \sprintf('/api/v1/works/%s/beta-reader-invitations', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$autora['token'],
        ], content: json_encode(['userId' => $lectora['userId']], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();

        $this->change($lectora['token'], ['betaReaderInvitations' => false]);

        $this->client->request('GET', '/api/v1/me/beta-reader-invitations', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->payload()['invitations'], 'Lo pendiente sigue pendiente.');
    }

    public function testWithoutASessionThereAreNoSettings(): void
    {
        $this->client->request('GET', '/api/v1/me/reception-settings');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsOf(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/reception-settings', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    /**
     * @param array<string, bool> $body
     *
     * @return array<string, mixed>
     */
    private function change(string $token, array $body): array
    {
        $this->client->request('PUT', '/api/v1/me/reception-settings', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();

        return $this->payload();
    }
}
