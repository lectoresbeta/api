<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use Symfony\Component\HttpFoundation\Response;

/**
 * El tema (`FEAT-USR-042`).
 *
 * La funcionalidad más pequeña del registro, y la que más fácil sería
 * justificar que no existiera: un tema cabe entero en el navegador.
 *
 * Existe por dos cosas, y son las dos que defienden estas pruebas: que
 * **sobrevive al dispositivo** y que **viaja en el contexto de sesión**, para
 * que la primera pintada ya sea la correcta en lugar de cambiar de color medio
 * segundo después.
 */
final class AppearanceSettingsTest extends EconomyScenario
{
    /** `RN-2`: quien nunca lo tocó recibe `SYSTEM`, no un hueco. */
    public function testWithoutHavingChosenItIsSystem(): void
    {
        $persona = $this->activatedPerson('persona');

        $this->read($persona['token']);

        self::assertResponseIsSuccessful();
        self::assertSame('SYSTEM', $this->payload()['theme']);
    }

    /**
     * **`RN-4`, y es la razón de que esto viva en el servidor.** El tema sale
     * en el contexto de sesión, que el layout ya pide en cada carga.
     */
    public function testTheThemeTravelsInTheSessionContext(): void
    {
        $persona = $this->activatedPerson('persona');

        $this->context($persona['token']);
        self::assertSame('SYSTEM', $this->payload()['theme']);

        $this->write($persona['token'], 'DARK');

        $this->context($persona['token']);
        self::assertSame('DARK', $this->payload()['theme']);
    }

    /**
     * Y sobrevive a la sesión, que es la otra mitad: quien eligió oscuro en un
     * sitio lo encuentra oscuro en otro.
     */
    public function testItSurvivesSigningOutAndBackIn(): void
    {
        $persona = $this->activatedPerson('persona');
        $this->write($persona['token'], 'DARK');

        $again = $this->signInAgain($persona['local'] ?? 'persona');

        $this->read($again);
        self::assertSame('DARK', $this->payload()['theme']);
    }

    public function testTheThreeValuesAreAccepted(): void
    {
        $persona = $this->activatedPerson('persona');

        foreach (['LIGHT', 'DARK', 'SYSTEM'] as $theme) {
            $this->write($persona['token'], $theme);

            self::assertSame($theme, $this->payload()['theme']);
        }
    }

    /**
     * `RN-3`: un valor desconocido **se rechaza nombrándolo**. Caer al por
     * defecto en silencio dejaría a quien manda `oscuro` creyendo que ha
     * cambiado algo.
     */
    public function testAnUnknownValueIsRefusedRatherThanIgnored(): void
    {
        $persona = $this->activatedPerson('persona');
        $this->write($persona['token'], 'DARK');

        foreach (['oscuro', 'dark', '', null] as $value) {
            $this->put($persona['token'], null === $value ? [] : ['theme' => $value]);

            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY, var_export($value, true));
            self::assertSame('UNKNOWN_THEME', $this->payload()['code']);
        }

        // Y no ha cambiado nada por el camino.
        $this->read($persona['token']);
        self::assertSame('DARK', $this->payload()['theme']);
    }

    /**
     * `RN-6`: es una preferencia de pantalla, no una operación sobre
     * contenido. Obligar a activar la cuenta para poner el modo oscuro sería
     * absurdo.
     */
    public function testItWorksBeforeActivatingTheAccount(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        $this->put($token, ['theme' => 'DARK']);

        self::assertResponseIsSuccessful();
        self::assertSame('DARK', $this->payload()['theme']);
    }

    /** `RN-1`: es de cada quien. */
    public function testEachPersonHasTheirOwn(): void
    {
        $una = $this->activatedPerson('una');
        $otra = $this->activatedPerson('otra');

        $this->write($una['token'], 'DARK');

        $this->read($otra['token']);
        self::assertSame('SYSTEM', $this->payload()['theme']);
    }

    /** `RN-5`: a nadie fuera de `User` le importa de qué color ve alguien la pantalla. */
    public function testChangingTheThemeAnnouncesNothing(): void
    {
        $persona = $this->activatedPerson('persona');

        $this->capture();
        $this->write($persona['token'], 'DARK');

        self::assertSame([], $this->capture());
    }

    public function testWithoutASessionThereIsNoTheme(): void
    {
        $this->client->request('GET', '/api/v1/me/appearance-settings');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('PUT', '/api/v1/me/appearance-settings');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function read(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/appearance-settings', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    private function context(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/context', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
    }

    private function write(string $token, string $theme): void
    {
        $this->put($token, ['theme' => $theme]);

        self::assertResponseIsSuccessful();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function put(string $token, array $body): void
    {
        $this->client->request('PUT', '/api/v1/me/appearance-settings', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function signInAgain(string $local): string
    {
        $this->client->request('POST', '/api/v1/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'email' => $this->address($local),
            'password' => self::PASSWORD,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        return (string) $this->payload()['accessToken'];
    }
}
