<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Shared\Infrastructure\Messenger\IntegrationEventSerializer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Mime\Email as MimeEmail;

/**
 * Cambiar o establecer la contraseña (`FEAT-USR-041`).
 *
 * Lo que de verdad se prueba aquí es que **la contraseña actual no es una
 * formalidad**: cambiarla echa a todas las sesiones, así que sin ese campo
 * quien se siente ante un portátil desbloqueado se queda con la cuenta.
 */
final class ChangePasswordTest extends WebTestCase
{
    private const ORIGINAL = 'Valida1!';

    private KernelBrowser $client;

    private string $run;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->run = bin2hex(random_bytes(5));
    }

    public function testChangingItWithTheCurrentOneWorks(): void
    {
        [$email, $token] = $this->activatedPerson('cambia');

        $this->changePassword($token, self::ORIGINAL, 'Nueva9!abc');

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertTrue($this->canLogIn($email, 'Nueva9!abc'));
        self::assertFalse($this->canLogIn($email, self::ORIGINAL));
    }

    /**
     * `RN-1`, la regla que sostiene todo lo demás. Una sesión abierta no
     * basta.
     */
    public function testWithoutTheCurrentOneItIsRefused(): void
    {
        [$email, $token] = $this->activatedPerson('sinactual');

        $this->changePassword($token, 'MeLaInvento9!', 'Nueva9!abc');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('INCORRECT_PASSWORD', $this->payload()['code']);

        $this->changePassword($token, null, 'Nueva9!abc');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('CURRENT_PASSWORD_REQUIRED', $this->payload()['code']);

        self::assertTrue($this->canLogIn($email, self::ORIGINAL), 'No ha cambiado nada.');
    }

    /**
     * `RN-3`: cerrar las sesiones. Quien cambia su contraseña muchas veces lo
     * hace porque sospecha, y dejar viva una sesión ajena sería dejar dentro
     * justo a quien se quiere echar.
     */
    public function testChangingItClosesTheSessions(): void
    {
        [$email, $token] = $this->activatedPerson('sesiones');
        $otra = $this->logIn($email, self::ORIGINAL);
        self::assertNotNull($otra);

        $this->changePassword($token, self::ORIGINAL, 'Nueva9!abc');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('POST', '/api/v1/auth/refresh', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['refreshToken' => $otra],
            \JSON_THROW_ON_ERROR,
        ));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * `RN-4`: el aviso por correo es lo único que le dice a alguien que le
     * han cambiado la contraseña. No se puede desactivar.
     */
    public function testChangingItWarnsByEmail(): void
    {
        [$email, $token] = $this->activatedPerson('avisa');

        $this->changePassword($token, self::ORIGINAL, 'Nueva9!abc');
        $this->consumePendingEvents();

        $avisos = self::getMailerMessages();
        self::assertCount(1, $avisos);
        self::assertInstanceOf(MimeEmail::class, $avisos[0]);
        self::assertSame($email, $avisos[0]->getTo()[0]->getAddress());
        self::assertStringContainsString('Has cambiado la contraseña', (string) $avisos[0]->getTextBody());
    }

    /**
     * `RN-6`: guardar sin tocar el campo no debería cerrar todas las sesiones
     * ni mandar un aviso de seguridad, así que se rechaza en vez de aceptarse
     * en silencio.
     */
    public function testTheNewOneCannotBeTheOldOne(): void
    {
        [, $token] = $this->activatedPerson('igual');

        $this->changePassword($token, self::ORIGINAL, self::ORIGINAL);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('PASSWORD_UNCHANGED', $this->payload()['code']);
    }

    public function testTheNewOneMustMeetThePolicy(): void
    {
        [, $token] = $this->activatedPerson('debil');

        $this->changePassword($token, self::ORIGINAL, 'corta');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('WEAK_PASSWORD', $this->payload()['code']);
    }

    /**
     * `RN-8`: el campo vacío solo vale en una cuenta **sin** contraseña. Es
     * lo que impide usar el camino de «establecer» para saltarse `RN-1`.
     *
     * La cuenta sin contraseña se fabrica aquí quitándosela: el alta con
     * Google es `FEAT-USR-002` y todavía no existe.
     */
    public function testAnAccountWithoutAPasswordSetsItWithTheFieldEmpty(): void
    {
        [$email, $token] = $this->activatedPerson('sinclave');
        $this->stripPasswordOf($email);

        $this->changePassword($token, null, 'Primera9!abc');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertTrue($this->canLogIn($email, 'Primera9!abc'));

        // Y a partir de aquí el campo vuelve a ser obligatorio.
        $this->changePassword($token, null, 'Segunda9!abc');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('CURRENT_PASSWORD_REQUIRED', $this->payload()['code']);
    }

    /**
     * Sin esto, el formulario es un sitio donde probar contraseñas contra una
     * sesión robada, sin las protecciones del login.
     */
    public function testRepeatedFailuresAreRateLimited(): void
    {
        [, $token] = $this->activatedPerson('fuerzabruta');

        for ($intento = 0; $intento < 5; ++$intento) {
            $this->changePassword($token, 'NoEsEsta9!', 'Nueva9!abc');
            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }

        $this->changePassword($token, 'NoEsEsta9!', 'Nueva9!abc');
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function testWithoutASessionThereIsNothingToChange(): void
    {
        $this->client->request('PUT', '/api/v1/me/password', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['currentPassword' => self::ORIGINAL, 'newPassword' => 'Nueva9!abc'],
            \JSON_THROW_ON_ERROR,
        ));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function changePassword(string $token, ?string $current, string $new): void
    {
        $body = ['newPassword' => $new];

        if (null !== $current) {
            $body['currentPassword'] = $current;
        }

        $this->client->request('PUT', '/api/v1/me/password', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{0: string, 1: string} el correo y un token de acceso
     */
    private function activatedPerson(string $local): array
    {
        $email = \sprintf('%s+%s@ejemplo.com', $local, $this->run);

        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => self::ORIGINAL,
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->consumePendingEvents();

        $this->client->request('POST', '/api/v1/auth/activate', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['token' => $this->tokenOfTheLastEmail()],
            \JSON_THROW_ON_ERROR,
        ));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->logIn($email, self::ORIGINAL);

        return [$email, (string) $this->payload()['accessToken']];
    }

    /**
     * Deja la cuenta sin contraseña, que es como nace una creada con Google.
     */
    private function stripPasswordOf(string $email): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->getConnection()->executeStatement(
            'UPDATE user_ctx.account SET password_hash = NULL WHERE email = :email',
            ['email' => $email],
        );
        $entityManager->clear();
    }

    private function canLogIn(string $email, string $password): bool
    {
        return null !== $this->logIn($email, $password);
    }

    private function logIn(string $email, string $password): ?string
    {
        $this->client->request('POST', '/api/v1/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => \sprintf('198.51.%d.%d', random_int(0, 255), random_int(1, 254)),
        ], content: json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR));

        if (!$this->client->getResponse()->isSuccessful()) {
            return null;
        }

        return (string) $this->payload()['refreshToken'];
    }

    private function tokenOfTheLastEmail(): string
    {
        $emails = self::getMailerMessages();
        self::assertNotEmpty($emails);

        $last = $emails[\count($emails) - 1];
        self::assertInstanceOf(MimeEmail::class, $last);
        self::assertSame(1, preg_match('/token=([0-9a-f]{64})/', (string) $last->getTextBody(), $matches));

        return $matches[1];
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

    private function consumePendingEvents(): void
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.integration');

        /** @var SerializerInterface $serializer */
        $serializer = self::getContainer()->get(IntegrationEventSerializer::class);

        /** @var MessageBusInterface $bus */
        $bus = self::getContainer()->get(MessageBusInterface::class);

        foreach ($transport->getSent() as $envelope) {
            /** @var array{body: string, headers: array<string, string>} $wire */
            $wire = $serializer->encode($envelope);
            $bus->dispatch($serializer->decode($wire)->with(new ReceivedStamp('integration')));
        }
    }
}
