<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * El ciclo de vida de la sesión (`FEAT-USR-004`).
 *
 * Dos cosas concentran casi todo el valor de este fichero, y ninguna se ve
 * mirando una respuesta correcta: que **el token no lleve nada dentro** y que
 * **fallar no diga quién existe**.
 */
final class SessionTest extends WebTestCase
{
    private const PASSWORD = 'Valida1!';

    private KernelBrowser $client;

    /**
     * Un sufijo distinto en cada ejecución.
     *
     * Los dos limitadores de frecuencia son reales —desactivarlos sería tanto
     * como no probarlos— y su contador vive en caché, así que sobrevive de un
     * `phpunit` al siguiente. Con correos y direcciones fijos, la tercera o
     * cuarta ejecución seguida empieza a recibir `429` en tests que no van de
     * eso, y el fallo parece de código.
     */
    private string $run;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->run = bin2hex(random_bytes(4));
    }

    public function testSigningInReturnsAShortLivedTokenAndARefreshToken(): void
    {
        $this->register($this->address('sesion'));

        $this->logIn($this->address('sesion'), self::PASSWORD);

        self::assertResponseIsSuccessful();

        $session = $this->payload();

        self::assertSame(900, $session['expiresIn']);
        self::assertNotSame('', $session['accessToken']);
        self::assertNotSame('', $session['refreshToken']);
    }

    /**
     * `decision:0007` `RN-4`, y el ADR pide explícitamente este test.
     *
     * Un rol dentro del token sobrevive a su propia retirada hasta quince
     * minutos. Por eso el token no lleva ninguno, ni nada personal: solo
     * quién, cuándo y hasta cuándo.
     */
    public function testTheAccessTokenCarriesNoRolesAndNoPersonalData(): void
    {
        $this->register($this->address('opaco'));
        $this->logIn($this->address('opaco'), self::PASSWORD);

        $claims = $this->claimsOf($this->payload()['accessToken']);

        self::assertSame(['exp', 'iat', 'jti', 'sub'], $this->sorted(array_keys($claims)));
        self::assertStringNotContainsString($this->address('opaco'), json_encode($claims, \JSON_THROW_ON_ERROR));
    }

    /**
     * El onboarding ocurre antes de activar (`FEAT-USR-001` `RN-9`), así que
     * impedirlo dejaría fuera a todo el mundo justo después de registrarse.
     */
    public function testAnUnactivatedAccountSignsInNormally(): void
    {
        $this->register($this->address('sinactivar'));

        $this->logIn($this->address('sinactivar'), self::PASSWORD);

        self::assertResponseIsSuccessful();
    }

    /**
     * La propiedad que sostiene `RN-2`: contraseña incorrecta y cuenta
     * inexistente son **la misma respuesta**, byte a byte.
     */
    public function testAWrongPasswordAndAnUnknownAddressAreIndistinguishable(): void
    {
        $this->register($this->address('existe'));

        $this->logIn($this->address('existe'), 'Incorrecta1!');
        $wrongPassword = $this->client->getResponse();

        $this->logIn('no-existe@ejemplo.com', 'Incorrecta1!');
        $unknownAccount = $this->client->getResponse();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $wrongPassword->getStatusCode());
        self::assertSame($wrongPassword->getStatusCode(), $unknownAccount->getStatusCode());
        self::assertSame($wrongPassword->getContent(), $unknownAccount->getContent());
    }

    /**
     * Una cuenta de Google no tiene contraseña. Decir «esa cuenta usa Google»
     * volvería a delatar qué correos existen (`RN-7`).
     */
    public function testAnAccountWithoutAPasswordAnswersLikeAnyOtherFailure(): void
    {
        $this->registerWithGoogle($this->address('google'));

        $this->logIn($this->address('google'), self::PASSWORD);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('INVALID_CREDENTIALS', $this->payload()['code']);
    }

    /**
     * Aquí sí se distingue, y a propósito (`RN-5`): solo llega quien ya ha
     * acertado la contraseña, y callarlo dejaría a una persona sancionada
     * creyendo que la ha olvidado.
     */
    public function testABlockedAccountIsToldThatItIsBlocked(): void
    {
        $this->register($this->address('bloqueada'));
        $this->block($this->address('bloqueada'));

        $this->logIn($this->address('bloqueada'), self::PASSWORD);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('ACCOUNT_BLOCKED', $this->payload()['code']);
    }

    public function testRenewingRotatesTheRefreshToken(): void
    {
        $this->register($this->address('rota'));
        $this->logIn($this->address('rota'), self::PASSWORD);

        $first = $this->payload()['refreshToken'];

        $this->renew($first);
        self::assertResponseIsSuccessful();

        $second = $this->payload()['refreshToken'];
        self::assertNotSame($first, $second);

        // El anterior deja de servir en el acto.
        $this->renew($first);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * `RN-11`. Que un token revocado vuelva a aparecer solo ocurre si dos
     * partes lo tienen, y no hay lectura benigna: se cierra todo.
     */
    public function testReusingARevokedRefreshTokenRevokesEverySession(): void
    {
        $this->register($this->address('robada'));
        $this->logIn($this->address('robada'), self::PASSWORD);
        $stolen = $this->payload()['refreshToken'];

        // Una segunda sesión de la misma persona, en otro dispositivo.
        $this->logIn($this->address('robada'), self::PASSWORD);
        $otherDevice = $this->payload()['refreshToken'];

        // La sesión robada se renueva una vez con normalidad...
        $this->renew($stolen);
        self::assertResponseIsSuccessful();

        // ...y alguien vuelve a presentar el token ya gastado.
        $this->renew($stolen);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        // El otro dispositivo también queda fuera. Es molesto y es el punto.
        $this->renew($otherDevice);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoggingOutRevokesTheRefreshTokenAndIsIdempotent(): void
    {
        $this->register($this->address('salida'));
        $this->logIn($this->address('salida'), self::PASSWORD);
        $refreshToken = $this->payload()['refreshToken'];

        $this->logOut($refreshToken);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->logOut($refreshToken);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->logOut('un-token-que-nunca-existio');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testRepeatedFailuresFromOneAddressAreRateLimited(): void
    {
        // Aquí sí se repite desde una misma dirección: es justo lo que se
        // está probando.
        $email = $this->address('fuerzabruta');
        $from = \sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(1, 254));

        $this->register($email);

        $statuses = [];

        for ($attempt = 0; $attempt < 12; ++$attempt) {
            $this->logIn($email, 'Incorrecta1!', $from);
            $statuses[] = $this->client->getResponse()->getStatusCode();
        }

        self::assertContains(Response::HTTP_TOO_MANY_REQUESTS, $statuses);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $statuses[0]);
    }

    /**
     * Una dirección de correo que solo existe en esta ejecución.
     */
    private function address(string $local): string
    {
        return \sprintf('%s+%s@ejemplo.com', $local, $this->run);
    }

    private function register(string $email): void
    {
        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => self::PASSWORD,
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
    }

    /**
     * Cada inicio de sesión llega desde **una dirección nueva**, porque son
     * accesos sin relación entre sí. El único test al que le importa repetir
     * desde la misma la fija a mano.
     */
    private function logIn(string $email, string $password, ?string $from = null): void
    {
        $from ??= \sprintf('198.51.%d.%d', random_int(0, 255), random_int(1, 254));

        $this->client->request('POST', '/api/v1/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => $from,
        ], content: json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR));
    }

    private function renew(string $refreshToken): void
    {
        $this->client->request('POST', '/api/v1/auth/refresh', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['refreshToken' => $refreshToken],
            \JSON_THROW_ON_ERROR,
        ));
    }

    private function logOut(string $refreshToken): void
    {
        $this->client->request('POST', '/api/v1/auth/logout', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['refreshToken' => $refreshToken],
            \JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * Da de alta una cuenta como las de Google: activa y **sin contraseña**.
     */
    private function registerWithGoogle(string $email): void
    {
        $this->register($email);

        $user = $this->users()->ofEmail(Email::fromString($email));
        self::assertNotNull($user);

        $manager = $this->doctrine()->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        $manager->getConnection()->update(
            'user_ctx.account',
            ['password_hash' => null, 'auth_provider' => 'GOOGLE', 'status' => 'ACTIVE'],
            ['id' => $user->id()->value()],
        );
        $manager->clear();
    }

    private function block(string $email): void
    {
        $user = $this->users()->ofEmail(Email::fromString($email));
        self::assertNotNull($user);

        $user->block(new \DateTimeImmutable());
        $this->users()->save($user);
        $this->doctrine()->getManager()->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function claimsOf(string $jwt): array
    {
        $parts = explode('.', $jwt);
        self::assertCount(3, $parts, 'El token de acceso no es un JWT.');

        /** @var array<string, mixed> $claims */
        $claims = json_decode(
            (string) base64_decode(strtr($parts[1], '-_', '+/'), true),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        return $claims;
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    private function users(): UserRepository
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);

        return $users;
    }

    private function doctrine(): ManagerRegistry
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');

        return $doctrine;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
