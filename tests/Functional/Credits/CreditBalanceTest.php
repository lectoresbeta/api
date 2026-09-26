<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Credits;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET /credits/balance` (`FEAT-CRD-001`).
 *
 * Es además el primer endpoint autenticado del proyecto, así que comprueba de
 * paso que el token de acceso sirve para algo: que el `sub` se resuelve
 * contra la base de datos y que sin token no se pasa.
 */
final class CreditBalanceTest extends WebTestCase
{
    private const PASSWORD = 'Valida1!';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
    }

    public function testWithoutATokenThereIsNoAnswer(): void
    {
        $this->client->request('GET', '/api/v1/credits/balance');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAMadeUpTokenIsRefused(): void
    {
        $this->client->request('GET', '/api/v1/credits/balance', server: [
            'HTTP_AUTHORIZATION' => 'Bearer no.es.un.token',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * `RN-2`, y es la regla que evita el error más probable de esta
     * operación: la cabecera de la aplicación la llama nada más iniciar
     * sesión, **antes** de que mucha gente haya activado su cuenta. Un `404`
     * ahí rompería la pantalla para todos ellos.
     */
    public function testSomebodyWithNoMovementsHasZeroAndNotAnError(): void
    {
        $token = $this->signUpAndSignIn('reciennacida@ejemplo.com');

        $this->balanceWith($token);

        self::assertResponseIsSuccessful();
        self::assertSame(['balance' => 0], $this->payload());
        self::assertSame('no-store, private', $this->client->getResponse()->headers->get('Cache-Control'));
    }

    private function signUpAndSignIn(string $email): string
    {
        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => self::PASSWORD,
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));

        $this->client->request('POST', '/api/v1/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '198.51.100.'.(1 + crc32($this->name()) % 200),
        ], content: json_encode(['email' => $email, 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        return (string) $this->payload()['accessToken'];
    }

    private function balanceWith(string $accessToken): void
    {
        $this->client->request('GET', '/api/v1/credits/balance', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);
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
