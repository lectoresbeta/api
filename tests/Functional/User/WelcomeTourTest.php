<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El tour de bienvenida de la Home (`FEAT-USR-026`).
 *
 * **El backend aporta poco pero imprescindible: recordar si ya lo vio.** Sin
 * eso, el tour reaparece en cada visita y se convierte en un incordio.
 *
 * Y lo guarda **en el servidor** (`RN-3`), que parece un detalle y no lo es:
 * en el navegador reaparecería en cada dispositivo y desaparecería al
 * limpiarlo. Es la diferencia entre una decisión de producto observable y una
 * preferencia invisible.
 */
final class WelcomeTourTest extends EconomyScenario
{
    /**
     * `RN-1`: quien acaba de llegar lo tiene pendiente.
     */
    public function testANewcomerHasItPending(): void
    {
        $recien = $this->activatedPerson('recien');

        $state = $this->tourState($recien['token']);

        self::assertSame('home', $state['tourId']);
        self::assertTrue($state['pending']);
        self::assertNull($state['lastStep']);
    }

    /**
     * `RN-1`: terminado, no vuelve.
     */
    public function testOnceFinishedItDoesNotComeBack(): void
    {
        $recien = $this->activatedPerson('recien');

        $this->finish($recien['token'], lastStep: 4, dismissed: false);

        self::assertFalse($this->tourState($recien['token'])['pending']);
    }

    /**
     * `RN-2`: **cerrarlo en el paso 2 cuenta como visto.**.
     *
     * Quien lo cierra ha decidido que no le interesa, y volver a enseñárselo
     * mañana sería no haberle escuchado.
     */
    public function testDismissingItHalfwayCountsAsSeen(): void
    {
        $recien = $this->activatedPerson('recien');

        $this->finish($recien['token'], lastStep: 2, dismissed: true);

        self::assertFalse($this->tourState($recien['token'])['pending']);
    }

    /**
     * `RN-4`: **se registra en qué paso se abandonó.**.
     *
     * Es la única métrica que dice si el tour funciona: sin ella, «lo vieron
     * entero» y «lo cerraron en el primer globo» serían el mismo dato.
     */
    public function testItRecordsWhereYouLeftOff(): void
    {
        $recien = $this->activatedPerson('recien');

        $this->finish($recien['token'], lastStep: 2, dismissed: true);

        self::assertSame(2, $this->tourState($recien['token'])['lastStep']);
    }

    /**
     * `RN-3`: el estado es **del servidor**, así que viaja con la cuenta y no
     * con el navegador. Una sesión nueva lo ve igual.
     */
    public function testTheStateFollowsTheAccountAndNotTheBrowser(): void
    {
        $email = $this->address('recien');
        $recien = $this->activatedPerson('recien');
        $this->finish($recien['token'], lastStep: 4, dismissed: false);

        // Otra sesión de la misma persona, como si entrara desde otro sitio.
        $this->client->request('POST', '/api/v1/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '198.51.100.7',
        ], content: json_encode(['email' => $email, 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $otraSesion = (string) $this->payload()['accessToken'];

        self::assertFalse($this->tourState($otraSesion)['pending']);
    }

    /**
     * El estado **también viaja en el contexto de sesión** (`FEAT-USR-027`),
     * para no añadir una petición más en cada carga del layout.
     */
    public function testItAlsoTravelsInTheSessionContext(): void
    {
        $recien = $this->activatedPerson('recien');

        self::assertSame(['home'], $this->sessionContext($recien['token'])['pendingTours']);

        $this->finish($recien['token'], lastStep: 4, dismissed: false);

        self::assertSame([], $this->sessionContext($recien['token'])['pendingTours']);
    }

    /**
     * `RN-6`: **funciona con la cuenta sin activar.** No es publicar
     * contenido, es decir que ya has visto algo, y una regla pensada para que
     * las cuentas sin verificar no escriban no debería condenarlas a los
     * mismos cuatro globos en cada visita.
     */
    public function testItWorksWithoutActivatingTheAccount(): void
    {
        $sinActivar = $this->signedInWithoutActivating('reciente');

        self::assertTrue($this->tourState($sinActivar)['pending']);

        $this->finish($sinActivar, lastStep: 4, dismissed: false);

        self::assertFalse($this->tourState($sinActivar)['pending']);
    }

    /**
     * Repetirlo no reabre nada ni cambia nada: es idempotente.
     */
    public function testFinishingItTwiceIsHarmless(): void
    {
        $recien = $this->activatedPerson('recien');

        $this->finish($recien['token'], lastStep: 4, dismissed: false);
        $this->finish($recien['token'], lastStep: 1, dismissed: true);

        self::assertFalse($this->tourState($recien['token'])['pending']);
    }

    /**
     * Un tour inventado **se rechaza**, no se ignora: crearía un tour
     * fantasma que nadie ha visto nunca y que se enseñaría para siempre.
     */
    public function testAnUnknownTourIsRefused(): void
    {
        $recien = $this->activatedPerson('recien');

        $this->client->request('GET', '/api/v1/me/tour?tourId=inventado', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$recien['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_TOUR', $this->payload()['code']);
    }

    /**
     * Y un paso que no está en el tour, por lo mismo: estropearía en silencio
     * la métrica que justifica guardarlo.
     */
    public function testAStepOutsideTheTourIsRefused(): void
    {
        $recien = $this->activatedPerson('recien');

        $this->client->request('POST', '/api/v1/me/tour/completion', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$recien['token'],
        ], content: json_encode(['lastStep' => 9, 'dismissed' => true], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('UNKNOWN_TOUR_STEP', $this->payload()['code']);
    }

    /**
     * Sin sesión, ninguna de las dos.
     */
    public function testBothRequireASession(): void
    {
        $this->client->request('GET', '/api/v1/me/tour');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('POST', '/api/v1/me/tour/completion');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array<string, mixed>
     */
    private function tourState(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/tour', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionContext(string $token): array
    {
        $this->client->request('GET', '/api/v1/me/context', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseIsSuccessful();

        return $this->payload();
    }

    private function finish(string $token, int $lastStep, bool $dismissed): void
    {
        $this->client->request('POST', '/api/v1/me/tour/completion', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['lastStep' => $lastStep, 'dismissed' => $dismissed], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}
