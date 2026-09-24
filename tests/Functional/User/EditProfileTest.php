<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use LectoresBeta\User\Account\Domain\ValueObject\Biography;
use Symfony\Component\HttpFoundation\Response;

/**
 * Editar el perfil (`FEAT-USR-008`).
 *
 * La pantalla presenta como un formulario uniforme cosas que no lo son, y eso
 * es lo que se defiende aquí: **el nombre de usuario y la foto no viajan en
 * este `PATCH`**, porque mezclados una biografía se quedaría sin guardar por
 * un nombre de usuario ocupado o por una subida fallida.
 *
 * Y la biografía se guarda como **texto plano**: es de los pocos campos
 * libres que una cuenta recién creada puede rellenar antes de tener
 * reputación alguna, lo que la convierte en el primer sitio donde alguien
 * intentará colocar un enlace.
 */
final class EditProfileTest extends EconomyScenario
{
    public function testTheProfileOpensWithWhatIsThere(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->myProfile($person['token']);

        self::assertResponseIsSuccessful();
        self::assertSame('Ana García', $this->payload()['name']);
        self::assertSame($person['username'], $this->payload()['username']);
        self::assertSame(
            ['userId', 'username', 'name', 'description', 'avatarUrl', 'coverUrl', 'usernameChangeableOn', 'counters'],
            array_keys($this->payload()),
        );
    }

    public function testTheProfileNeverCarriesPrivateData(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->myProfile($person['token']);

        $body = json_encode($this->payload(), \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('@ejemplo.com', $body, 'Ni el correo propio.');
        self::assertStringNotContainsString('1990', $body, 'Ni la fecha de nacimiento.');
    }

    public function testChangingTheNameAndTheBiographyShowsUpInThePublicProfile(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->patch($person['token'], ['name' => 'Ana G.', 'description' => 'Escribo relatos breves.']);

        self::assertResponseIsSuccessful();
        self::assertSame('Ana G.', $this->payload()['name']);
        self::assertSame('Escribo relatos breves.', $this->payload()['description']);

        $this->client->request('GET', \sprintf('/api/v1/users/%s', $person['userId']));
        self::assertResponseIsSuccessful();
        self::assertSame('Ana G.', $this->payload()['name']);
        self::assertSame('Escribo relatos breves.', $this->payload()['description']);
    }

    /**
     * Es un `PATCH`: **lo que no se envía se queda como estaba**. Un `PUT`
     * obligaría a la edición en línea del perfil a reenviar campos que no
     * está tocando, que es como se borra una biografía sin querer.
     */
    public function testWhatIsNotSentIsLeftAlone(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');
        $this->patch($person['token'], ['description' => 'Escribo relatos breves.']);

        $this->patch($person['token'], ['name' => 'Ana G.']);

        self::assertSame('Ana G.', $this->payload()['name']);
        self::assertSame('Escribo relatos breves.', $this->payload()['description'], 'Sigue ahí.');
    }

    /**
     * Y enviar `null` **sí** la borra: no enviarla y enviarla vacía son cosas
     * distintas, y el cuerpo las distingue por la presencia de la clave.
     */
    public function testSendingNullClearsTheBiography(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');
        $this->patch($person['token'], ['description' => 'Escribo relatos breves.']);

        $this->patch($person['token'], ['description' => null]);

        self::assertNull($this->payload()['description']);
        self::assertSame('Ana García', $this->payload()['name'], 'Sin tocar el nombre.');
    }

    /**
     * `RN-2`: el nombre es lo que identifica a la persona en toda la
     * interfaz, así que no puede quedar vacío.
     */
    public function testAnEmptyNameIsRefused(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->patch($person['token'], ['name' => '   ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('INVALID_VALUE', $this->payload()['code']);

        $this->myProfile($person['token']);
        self::assertSame('Ana García', $this->payload()['name'], 'No se ha guardado nada.');
    }

    /**
     * **`RN-4`.** El marcado se retira en vez de rechazarse: lo que hay que
     * garantizar es que no sobreviva nada que un cliente pueda interpretar, y
     * decirle «su texto contiene HTML» a quien pegó desde un procesador de
     * textos no ayuda a nadie.
     */
    public function testTheBiographyIsStoredAsPlainText(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->patch($person['token'], [
            'description' => 'Escribo <a href="http://spam.example">relatos</a> <b>breves</b>.',
        ]);

        self::assertResponseIsSuccessful();
        $stored = (string) $this->payload()['description'];

        self::assertStringNotContainsString('<a', $stored);
        self::assertStringNotContainsString('href', $stored);
        self::assertStringNotContainsString('<b>', $stored);
        self::assertStringContainsString('relatos', $stored, 'El texto se conserva.');
        self::assertStringContainsString('breves', $stored);
    }

    /**
     * El límite se mide **después** de limpiar: contar las etiquetas contra
     * los 300 castigaría a quien pega desde un procesador de textos por algo
     * que ni siquiera se va a guardar.
     */
    public function testTheLimitIsMeasuredOnWhatIsActuallyStored(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $cabe = str_repeat('a', Biography::MAX_LENGTH);
        $this->patch($person['token'], ['description' => \sprintf('<b>%s</b>', $cabe)]);

        self::assertResponseIsSuccessful();
        self::assertSame($cabe, $this->payload()['description']);

        $this->patch($person['token'], ['description' => str_repeat('a', Biography::MAX_LENGTH + 1)]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('INVALID_VALUE', $this->payload()['code']);
    }

    /**
     * `RN-5`: son campos distintos. Uno se lee, el otro se escribe en la URL.
     */
    public function testChangingTheNameDoesNotTouchTheUsername(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->patch($person['token'], ['name' => 'Otra Persona']);

        self::assertSame($person['username'], $this->payload()['username']);

        // Y la URL del perfil sigue resolviendo por el mismo nombre.
        $this->client->request('GET', \sprintf('/api/v1/profiles/%s', $person['username']));
        self::assertResponseIsSuccessful();
        self::assertSame('Otra Persona', $this->payload()['name']);
    }

    /**
     * El nombre de usuario **no se cambia por aquí** aunque la pantalla lo
     * enseñe junto a los demás campos: tiene su propio endpoint porque sus
     * consecuencias duran 30 días.
     */
    public function testTheUsernameCannotBeChangedThroughThisEndpoint(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->patch($person['token'], ['username' => 'otronombre', 'name' => 'Ana G.']);

        self::assertResponseIsSuccessful();
        self::assertSame($person['username'], $this->payload()['username'], 'Se ignora.');
        self::assertSame('Ana G.', $this->payload()['name'], 'Y el resto se guarda igual.');
    }

    /**
     * **`S-29`, resuelta: hace falta activar la cuenta.**.
     *
     * La tentación era lo contrario, porque el nombre ya se fija antes de
     * activar y prohibir corregir una errata parece incoherente. Decide la
     * biografía: es texto libre que aparece en un perfil público, y dejar
     * publicarlo a una cuenta cuyo correo nadie ha verificado es justo lo que
     * `decision:0003` existe para impedir — el primer sitio donde alguien
     * intentará colocar un enlace es precisamente ese campo.
     *
     * El onboarding es la excepción que compra la entrada, y no tiene ningún
     * campo libre.
     */
    public function testAnUnactivatedAccountCannotPublishABiography(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        $this->patch($token, ['name' => 'Ana García', 'description' => 'Visita http://spam.example']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_NOT_ACTIVATED', $this->payload()['code']);
    }

    /**
     * Y leer sí: es solo lectura, y la pantalla de configuración tiene que
     * poder abrirse para enseñar el botón de activar.
     */
    public function testAnUnactivatedAccountMayStillOpenItsOwnProfile(): void
    {
        $token = $this->signedInWithoutActivating('pendiente');

        $this->myProfile($token);

        self::assertResponseIsSuccessful();
    }

    public function testWithoutASessionThereIsNoProfileToEdit(): void
    {
        $this->client->request('GET', '/api/v1/me/profile');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request('PATCH', '/api/v1/me/profile', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testSavingAnnouncesTheNewPublicData(): void
    {
        $person = $this->namedPerson('persona', 'Ana García');

        $this->patch($person['token'], ['name' => 'Ana G.']);

        $announced = $this->lastAnnouncementOf('UserProfileUpdated');

        self::assertSame($person['userId'], $announced['userId']);
        self::assertSame('Ana G.', $announced['name']);
        self::assertArrayNotHasKey('email', $announced, 'Nada privado viaja.');
        self::assertArrayNotHasKey('birthDate', $announced);
    }

    /**
     * @return array{token: string, userId: string, username: string}
     */
    private function namedPerson(string $local, string $name): array
    {
        $person = $this->activatedPerson($local);

        $this->client->request('PUT', '/api/v1/me/onboarding/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ], content: json_encode(['name' => $name, 'birthDate' => '1990-05-17'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        $this->client->request('GET', '/api/v1/me/onboarding', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$person['token'],
        ]);
        self::assertResponseIsSuccessful();

        return [...$person, 'username' => (string) $this->payload()['username']];
    }

    private function myProfile(string $token): void
    {
        $this->client->request('GET', '/api/v1/me/profile', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param array<string, string|null> $body
     */
    private function patch(string $token, array $body): void
    {
        $this->client->request('PATCH', '/api/v1/me/profile', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        $this->capture();
    }
}
