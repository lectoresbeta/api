<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Shared\Infrastructure\Messenger\IntegrationEventSerializer;
use LectoresBeta\User\Account\Application\Contract\PasswordResetLinkProvider;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Mime\Email as MimeEmail;

/**
 * Recuperar la contraseña (`FEAT-USR-007`).
 *
 * **Es el único camino de vuelta a una cuenta.** Sin esto, quien olvida su
 * contraseña se queda fuera para siempre, así que lo que aquí se prueba no es
 * una comodidad: es que la puerta existe y que solo la abre quien controla el
 * buzón.
 *
 * Cada caso usa una dirección distinta: los contadores de frecuencia viven en
 * caché con la dirección por clave.
 */
final class RecoverPasswordTest extends WebTestCase
{
    private const ORIGINAL = 'Valida1!';
    private const NUEVA = 'Nueva9!';

    private KernelBrowser $client;

    private string $run;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->run = bin2hex(random_bytes(5));
    }

    /**
     * El recorrido entero: pedir, abrir el enlace, entrar con la nueva.
     */
    public function testTheWholeWayBackIntoAnAccount(): void
    {
        $email = $this->activatedAccount('olvidadiza');

        $this->forgotten($email);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->consumePendingEvents();
        $token = $this->tokenOfTheLastEmail();

        $this->reset($token, 'Nueva9!abc');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertTrue($this->canLogIn($email, 'Nueva9!abc'), 'Con la nueva, entra.');
        self::assertFalse($this->canLogIn($email, self::ORIGINAL), 'Y la anterior deja de servir.');
    }

    /**
     * `RN-7`: de un solo uso. Sin esto, un enlace reenviado por error o
     * recuperado del historial del navegador seguiría abriendo la cuenta.
     */
    public function testTheLinkDoesNotWorkTwice(): void
    {
        $email = $this->activatedAccount('unavez');
        $token = $this->linkFor($email);

        $this->reset($token, 'Primera1!');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->reset($token, 'Segunda2!');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('INVALID_PASSWORD_RESET_TOKEN', $this->payload()['code']);

        self::assertTrue($this->canLogIn($email, 'Primera1!'), 'Sigue valiendo la primera.');
    }

    /**
     * `RN-8`: pedirlo otra vez invalida el anterior. Dos enlaces vivos a la
     * vez doblarían la ventana en la que uno filtrado sirve para quedarse con
     * la cuenta.
     */
    public function testAskingAgainInvalidatesThePreviousLink(): void
    {
        $email = $this->activatedAccount('insistente');
        $primero = $this->linkFor($email);

        // El intervalo mínimo vigila la dirección, así que el segundo enlace
        // se emite desde el contrato, que es por donde lo pide el correo.
        $segundo = $this->issueDirectly($email);

        $this->reset($primero, 'Primera1!');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, 'El primero ya no vale.');

        $this->reset($segundo, 'Segunda2!');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT, 'Y el último sí.');
    }

    /**
     * `RN-10`: cerrar las demás sesiones no es un extra. Quien recupera su
     * contraseña muchas veces lo hace porque sospecha que alguien más entró.
     */
    public function testResettingClosesEveryOtherSession(): void
    {
        $email = $this->activatedAccount('invadida');
        $intrusa = $this->logIn($email, self::ORIGINAL);
        self::assertNotNull($intrusa);

        $this->reset($this->linkFor($email), self::NUEVA.'xyz');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('POST', '/api/v1/auth/refresh', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['refreshToken' => $intrusa],
            \JSON_THROW_ON_ERROR,
        ));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED, 'La sesión de quien estuviera dentro se cae.');
    }

    /**
     * `RN-14`, la regla menos evidente: usar el enlace demuestra que el buzón
     * es tuyo, que es exactamente lo que demuestra activar. Sin esto quedaría
     * un callejón: alguien entra y sigue sin poder escribir sin entender por
     * qué.
     */
    public function testRecoveringAlsoActivatesAnAccountThatWasNeverActivated(): void
    {
        $email = $this->register('sinactivar');

        $this->reset($this->linkFor($email), 'Nueva9!abc');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertSame(AccountStatus::ACTIVE, $this->accountOf($email)->status());
    }

    /**
     * `RN-1` y `RN-2`: una dirección sin registrar y un correo mal escrito
     * responden exactamente igual que una cuenta real. Si no, el formulario
     * diría quién tiene cuenta.
     */
    public function testAskingSaysNothingAboutWhoHasAnAccount(): void
    {
        $email = $this->activatedAccount('existente');

        $this->forgotten($email);
        $conCuenta = $this->client->getResponse();
        $this->consumePendingEvents();
        $correos = self::getMailerMessages();

        $this->forgotten(\sprintf('nadie+%s@ejemplo.com', $this->run));
        $sinCuenta = $this->client->getResponse();
        $this->consumePendingEvents();
        $paraNadie = self::getMailerMessages();

        $this->forgotten('esto-no-es-un-correo');
        $malEscrito = $this->client->getResponse();

        self::assertSame($conCuenta->getStatusCode(), $sinCuenta->getStatusCode());
        self::assertSame($conCuenta->getContent(), $sinCuenta->getContent());
        self::assertSame($conCuenta->getStatusCode(), $malEscrito->getStatusCode());

        self::assertCount(1, $correos);
        self::assertCount(0, $paraNadie, 'La diferencia está donde no se ve.');
    }

    /**
     * `RN-11`: el aviso que hace que robar una cuenta se note. Es lo único
     * que se lo dice a su dueño.
     */
    public function testChangingThePasswordWarnsByEmail(): void
    {
        $email = $this->activatedAccount('avisada');

        $this->reset($this->linkFor($email), 'Nueva9!abc');
        $this->consumePendingEvents();

        $aviso = self::getMailerMessages();
        self::assertCount(1, $aviso);
        self::assertInstanceOf(MimeEmail::class, $aviso[0]);
        self::assertSame($email, $aviso[0]->getTo()[0]->getAddress());
        self::assertStringContainsString('Si no has sido tú', (string) $aviso[0]->getTextBody());
    }

    /**
     * `RN-9`: una contraseña débil se rechaza **sin consumir el enlace**.
     * Quien se equivoca escribiéndola vuelve a intentarlo con el mismo correo
     * delante, en vez de tener que pedir otro.
     */
    public function testAWeakPasswordIsRefusedWithoutBurningTheLink(): void
    {
        $email = $this->activatedAccount('debil');
        $token = $this->linkFor($email);

        $this->reset($token, 'corta');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->reset($token, 'Buena9!abc');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT, 'El enlace seguía vivo.');
    }

    public function testAnInventedTokenIsRefused(): void
    {
        $this->reset(str_repeat('a', 64), 'Nueva9!abc');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('INVALID_PASSWORD_RESET_TOKEN', $this->payload()['code']);
    }

    /**
     * `RN-3`: una avalancha de correos «restablece tu contraseña» no solo es
     * spam, es como se prepara un engaño.
     */
    public function testThereIsRateLimitingWithTwoDistinguishableCodes(): void
    {
        $email = $this->activatedAccount('pesada');

        $this->forgotten($email);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->forgotten($email);
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertSame('PASSWORD_RESET_TOO_SOON', $this->payload()['code']);
        self::assertNotNull($this->client->getResponse()->headers->get('Retry-After'));

        $this->forgotten($email);
        $this->forgotten($email);

        self::assertSame('PASSWORD_RESET_LIMIT_REACHED', $this->payload()['code']);
    }

    private function activatedAccount(string $local): string
    {
        $email = $this->register($local);

        $this->client->request('POST', '/api/v1/auth/activate', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['token' => $this->tokenOfTheLastEmail()],
            \JSON_THROW_ON_ERROR,
        ));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        return $email;
    }

    private function register(string $local): string
    {
        $email = \sprintf('%s+%s@ejemplo.com', $local, $this->run);

        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => self::ORIGINAL,
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->consumePendingEvents();

        return $email;
    }

    /**
     * Pide el enlace y devuelve el token que ha llegado por correo.
     */
    private function linkFor(string $email): string
    {
        $this->forgotten($email);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->consumePendingEvents();

        return $this->tokenOfTheLastEmail();
    }

    /**
     * Un enlace más, por el contrato publicado y sin pasar por el endpoint:
     * es lo que hace `Notification` al enviar, y evita chocar con el
     * intervalo mínimo del formulario.
     */
    private function issueDirectly(string $email): string
    {
        /** @var PasswordResetLinkProvider $links */
        $links = self::getContainer()->get(PasswordResetLinkProvider::class);
        $link = $links->issueFor($this->accountOf($email)->id()->value());

        self::assertNotNull($link);

        return $link->token;
    }

    private function forgotten(string $email): void
    {
        $this->client->request('POST', '/api/v1/auth/password/forgotten', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => \sprintf('198.51.%d.%d', random_int(0, 255), random_int(1, 254)),
        ], content: json_encode(['email' => $email], \JSON_THROW_ON_ERROR));
    }

    private function reset(string $token, string $password): void
    {
        $this->client->request('POST', '/api/v1/auth/password/reset', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['token' => $token, 'password' => $password],
            \JSON_THROW_ON_ERROR,
        ));
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

    private function accountOf(string $email): User
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $user = $users->ofEmail(Email::fromString($email));

        self::assertNotNull($user);

        return $user;
    }

    private function tokenOfTheLastEmail(): string
    {
        $emails = self::getMailerMessages();
        self::assertNotEmpty($emails, 'No ha salido ningún correo.');

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
