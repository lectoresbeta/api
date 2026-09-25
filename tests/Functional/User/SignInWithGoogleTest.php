<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Tests\Functional\Support\EconomyScenario;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Enum\AuthProvider;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Authentication\Application\DTO\ExternalIdentity;
use LectoresBeta\User\Authentication\Application\Port\OAuthProvider;
use LectoresBeta\User\Authentication\Application\Service\OAuthProviders;
use LectoresBeta\User\Authentication\Domain\Exception\ExternalSignInFailed;
use LectoresBeta\User\Legal\Domain\Repository\LegalAcceptanceRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrar con Google (`FEAT-USR-002`).
 *
 * **La afirmación que estas pruebas defienden es `RN-3`**: Google acredita
 * quién es alguien, no qué ha aceptado. Es lo que es fácil dar por hecho —que
 * «entrar con Google» sustituye al registro— y no es así: Google dice que esa
 * persona controla ese correo; no dice que haya leído nada.
 *
 * De ahí salen los dos casos que más importan: crear una cuenta exige la
 * aceptación legal igual que el alta con correo, y **iniciar sesión no la
 * vuelve a pedir**.
 *
 * El proveedor se sustituye por uno de mentira, que es lo que permite
 * comprobar el comportamiento sin hablar con Google. Que baste con eso es a
 * su vez un criterio de aceptación de la ficha: añadir un proveedor no obliga
 * a tocar `Application` ni `Domain`.
 */
final class SignInWithGoogleTest extends EconomyScenario
{
    private const VERSIONS = ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'];

    private ?FakeOAuthProvider $provider = null;

    private bool $registered = false;

    /**
     * El camino entero: cuenta nueva, activada de nacimiento y con sus
     * créditos de bienvenida (`RN-8`, `OB-11`).
     *
     * Google ya ha comprobado que esa dirección es de quien la usa: mandar un
     * correo de activación sería pedirle a alguien que demuestre algo ya
     * demostrado, y perder gente en un paso que no añade seguridad.
     */
    public function testANewAccountIsBornActivatedAndWithItsWelcomeCredits(): void
    {
        $this->providerSays('google-1', 'nueva@ejemplo.com');

        $this->comeBack(['code' => 'ok', 'acceptedLegalVersions' => self::VERSIONS]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertTrue($this->payload()['isNewAccount']);
        self::assertFalse($this->payload()['linkedToExistingAccount']);
        self::assertNotSame('', $this->payload()['accessToken']);

        $this->consumeEverything();

        $user = $this->userOf('nueva@ejemplo.com');
        self::assertSame(AccountStatus::ACTIVE, $user->status(), 'Sin correo de activación de por medio.');
        self::assertSame(AuthProvider::GOOGLE, $user->authProvider());
        self::assertSame(10, $this->balanceOf($user->id()->value()), 'Y con los diez de bienvenida.');
    }

    /**
     * `RN-1` y `RN-3`, el corazón de la ficha: **sin aceptación no hay
     * cuenta**, y no se persiste nada.
     */
    public function testWithoutLegalAcceptanceNoAccountIsCreated(): void
    {
        $this->providerSays('google-2', 'sinaceptar@ejemplo.com');

        $this->comeBack(['code' => 'ok']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('TERMS_NOT_ACCEPTED', $this->payload()['code']);

        self::assertNull(
            $this->users()->ofEmail(Email::fromString('sinaceptar@ejemplo.com')),
            'Ni media cuenta: Google acredita quién eres, no qué aceptas.',
        );
    }

    /**
     * `RN-2`: la aceptación se guarda **con la versión de cada documento**,
     * igual que en el alta con correo. Un `accepted: true` sin versión no
     * demuestra nada.
     */
    public function testTheAcceptanceIsStoredWithItsVersions(): void
    {
        $this->providerSays('google-3', 'conversion@ejemplo.com');

        $this->comeBack(['code' => 'ok', 'acceptedLegalVersions' => self::VERSIONS]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $user = $this->userOf('conversion@ejemplo.com');

        /** @var LegalAcceptanceRepository $acceptances */
        $acceptances = self::getContainer()->get(LegalAcceptanceRepository::class);
        $stored = $acceptances->ofUser($user->id());

        self::assertCount(2, $stored);

        foreach ($stored as $acceptance) {
            self::assertSame('2026-01-15', $acceptance->version());
        }
    }

    /**
     * `RN-4`: **quien solo inicia sesión no vuelve a aceptar nada.** Se acepta
     * una vez, al crear la cuenta.
     *
     * Y de paso `RN-5` del criterio de aceptación: dos vueltas con la misma
     * identidad de Google no crean dos cuentas.
     */
    public function testSigningInAgainNeitherAsksForTermsNorCreatesASecondAccount(): void
    {
        $this->providerSays('google-4', 'repetida@ejemplo.com');

        $this->comeBack(['code' => 'ok', 'acceptedLegalVersions' => self::VERSIONS]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $primera = $this->userOf('repetida@ejemplo.com')->id()->value();

        // Sin mandar versiones legales esta vez.
        $this->comeBack(['code' => 'otro']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK, 'Entrar no es crear.');
        self::assertFalse($this->payload()['isNewAccount']);
        self::assertSame($primera, $this->userOf('repetida@ejemplo.com')->id()->value());
    }

    /**
     * `RN-6`, resuelta: si el correo ya tiene cuenta aquí, **se enlaza solo
     * si Google afirma que ese correo está verificado**.
     *
     * Sin esa afirmación, «tengo una cuenta con tu dirección» no demuestra
     * nada, y enlazar sería entregarle una cuenta ajena a quien supiera el
     * correo de su dueño.
     */
    public function testAnExistingEmailIsOnlyLinkedWhenTheProviderVouchesForIt(): void
    {
        $persona = $this->activatedPerson('persona');
        $email = $this->address('persona');

        $this->providerSays('google-5', $email, verified: false);
        $this->comeBack(['code' => 'ok']);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('EMAIL_ALREADY_REGISTERED', $this->payload()['code']);
        self::assertSame(AuthProvider::LOCAL, $this->userOf($email)->authProvider(), 'Sin enlazar.');

        // Y ahora Google sí lo afirma.
        $this->providerSays('google-5', $email, verified: true);
        $this->comeBack(['code' => 'ok']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertTrue($this->payload()['linkedToExistingAccount']);

        $user = $this->userOf($email);
        self::assertSame(AuthProvider::GOOGLE, $user->authProvider());
        self::assertSame($persona['userId'], $user->id()->value(), 'Es la misma cuenta, no una nueva.');
    }

    /**
     * Enlazar **no quita la contraseña**. Quitársela a quien ya entraba con
     * ella sería cerrarle la puerta que usa por haber probado otra, y no
     * protege nada: quien acaba de demostrar que controla ese correo podría
     * restablecerla en un minuto.
     */
    public function testLinkingKeepsThePasswordThatAlreadyWorked(): void
    {
        $this->activatedPerson('conclave');
        $email = $this->address('conclave');

        $this->providerSays('google-6', $email, verified: true);
        $this->comeBack(['code' => 'ok']);
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/api/v1/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '198.51.100.7',
        ], content: json_encode(['email' => $email, 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful('La contraseña de siempre sigue entrando.');
    }

    /**
     * Sin correo no hay cuenta: es la identidad de la cuenta aquí, y todo lo
     * que se le manda a alguien va ahí.
     */
    public function testWithoutAnEmailThereIsNothingToCreate(): void
    {
        $this->providerSays('google-7', null);

        $this->comeBack(['code' => 'ok', 'acceptedLegalVersions' => self::VERSIONS]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('EMAIL_NOT_SHARED', $this->payload()['code']);
    }

    /**
     * Un proveedor caído responde `502` con un mensaje genérico: lo que falle
     * al otro lado es detalle técnico de una integración, y contarlo aquí
     * sería exponerlo a cualquiera que pulse un botón.
     */
    public function testAProviderThatIsDownSaysSoWithoutSayingWhy(): void
    {
        $this->providerSays('google-down', 'caido@ejemplo.com');
        $this->provider?->breakDown();

        $this->comeBack(['code' => 'ok', 'acceptedLegalVersions' => self::VERSIONS]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_GATEWAY);
        self::assertSame('AUTH_PROVIDER_UNAVAILABLE', $this->payload()['code']);
        self::assertStringNotContainsString('token', strtolower((string) $this->payload()['detail']));
    }

    /**
     * Un proveedor que no existe es un `404`, no un `422`: el cliente ha
     * pedido una puerta que no hay, no ha escrito mal un campo.
     */
    public function testAnUnknownProviderIsNotThere(): void
    {
        $this->client->request('GET', '/api/v1/auth/oauth/facebook');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('UNKNOWN_AUTH_PROVIDER', $this->payload()['code']);
    }

    /**
     * El arranque: la dirección del proveedor y un `state` impredecible, sin
     * sesión y sin redirección.
     */
    public function testStartingHandsBackAnAddressAndAState(): void
    {
        $this->client->request('GET', '/api/v1/auth/oauth/google?redirectUri=https://app.test/vuelta');

        self::assertResponseIsSuccessful('Sin sesión: no puede haberla todavía.');

        $url = (string) $this->payload()['authorizationUrl'];

        self::assertStringStartsWith('https://accounts.google.com/', $url);
        self::assertStringContainsString('redirect_uri='.rawurlencode('https://app.test/vuelta'), $url);
        self::assertStringContainsString('state='.rawurlencode((string) $this->payload()['state']), $url);
        self::assertNotSame('', $this->payload()['state']);
    }

    /**
     * Una cuenta expulsada no estrena forma de entrar: entrar por otra puerta
     * no es una forma de saltarse una sanción.
     */
    public function testABlockedAccountDoesNotGetInThroughGoogleEither(): void
    {
        $persona = $this->activatedPerson('expulsada');
        $email = $this->address('expulsada');

        $user = $this->userOf($email);
        $user->block(new \DateTimeImmutable());
        $this->flush();

        $this->providerSays('google-8', $email, verified: true);
        $this->comeBack(['code' => 'ok']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(
            AuthProvider::LOCAL,
            $this->userOf($email)->authProvider(),
            'Y no se ha llegado a enlazar nada.',
        );
        self::assertNotSame('', $persona['userId']);
    }

    /**
     * El doble es **uno solo y mutable**, y no uno nuevo por cada respuesta:
     * el contenedor de pruebas no deja sustituir un servicio que ya se ha
     * usado, y hay casos que necesitan que Google cambie de opinión a mitad
     * —primero sin afirmar que el correo está verificado, después
     * afirmándolo— que es exactamente lo que hay que comprobar.
     */
    private function providerSays(string $externalId, ?string $email, bool $verified = true): void
    {
        $this->provider ??= new FakeOAuthProvider();

        $this->provider->externalId = $externalId;
        $this->provider->email = $email;
        $this->provider->verified = $verified;

        $this->useProvider($this->provider);
    }

    private function useProvider(OAuthProvider $provider): void
    {
        if ($this->registered) {
            return;
        }

        self::getContainer()->set(OAuthProviders::class, new OAuthProviders([$provider]));
        $this->registered = true;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function comeBack(array $body): void
    {
        $this->client->request('POST', '/api/v1/auth/oauth/google/callback', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => \sprintf('198.51.%d.%d', random_int(0, 255), random_int(1, 254)),
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        $this->capture();
    }

    private function users(): UserRepository
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);

        return $users;
    }

    private function userOf(string $email): \LectoresBeta\User\Account\Domain\Entity\User
    {
        $user = $this->users()->ofEmail(Email::fromString($email));

        self::assertNotNull($user, \sprintf('No hay cuenta para %s.', $email));

        return $user;
    }

    private function flush(): void
    {
        /** @var \Doctrine\Persistence\ManagerRegistry $registry */
        $registry = self::getContainer()->get('doctrine');
        $registry->getManager()->flush();
    }
}

/**
 * Google, de mentira.
 *
 * Es una clase con nombre y no una anónima porque tiene que poder cambiar de
 * respuesta entre dos llamadas de la misma prueba: el contenedor de pruebas
 * no deja sustituir un servicio que ya se ha usado.
 */
final class FakeOAuthProvider implements OAuthProvider
{
    public string $externalId = 'google-0';

    public ?string $email = null;

    public bool $verified = true;

    private bool $down = false;

    public function breakDown(): void
    {
        $this->down = true;
    }

    public function handles(): AuthProvider
    {
        return AuthProvider::GOOGLE;
    }

    public function authorizationUrl(string $state, ?string $redirectUri): string
    {
        return 'https://example.test/authorize?state='.$state;
    }

    public function identify(string $code, ?string $redirectUri): ExternalIdentity
    {
        if ($this->down) {
            throw ExternalSignInFailed::providerUnavailable();
        }

        return new ExternalIdentity($this->externalId, $this->email, $this->verified);
    }
}
