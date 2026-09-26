<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Shared\Infrastructure\Messenger\IntegrationEventSerializer;
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
 * Cambiar el correo de la cuenta (`FEAT-USR-040`).
 *
 * **Es un mecanismo de seguridad y no un trámite**, y eso son dos reglas: el
 * aviso a la dirección anterior y el cierre de sesiones al confirmar. Sin
 * ellas, el flujo se limita a verificar que el correo nuevo existe, que es
 * justo lo que un atacante puede demostrar.
 */
final class ChangeEmailTest extends WebTestCase
{
    private const PASSWORD = 'Valida1!';

    private KernelBrowser $client;

    private string $run;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->run = bin2hex(random_bytes(5));
    }

    public function testTheAddressOnlyChangesOnceConfirmed(): void
    {
        [$antiguo, $token] = $this->activatedPerson('titular');
        $nuevo = $this->address('nuevo');

        $this->requestChange($token, $nuevo, self::PASSWORD);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->consumePendingEvents();

        // El enlace se recoge ahora: cada petición vacía el buzón del test.
        $confirmacion = $this->confirmationTokenOf($nuevo);

        // Todavía no ha cambiado: se sigue entrando con el anterior.
        self::assertSame($antiguo, $this->accountEmail($antiguo));
        self::assertTrue($this->canLogIn($antiguo, self::PASSWORD));

        $this->confirm($confirmacion);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::assertTrue($this->canLogIn($nuevo, self::PASSWORD), 'Ahora entra con el nuevo.');
        self::assertFalse($this->canLogIn($antiguo, self::PASSWORD), 'Y no con el anterior.');
    }

    /**
     * `RN-3`: el aviso a la dirección anterior es la única defensa de quien
     * ha perdido el control de su sesión.
     */
    public function testThePreviousAddressIsWarned(): void
    {
        [$antiguo, $token] = $this->activatedPerson('avisada');
        $nuevo = $this->address('destino');

        $this->requestChange($token, $nuevo, self::PASSWORD);
        $this->consumePendingEvents();

        $correos = self::getMailerMessages();
        self::assertCount(2, $correos, 'El enlace y el aviso.');

        $destinos = array_map(
            static fn (MimeEmail $correo): string => $correo->getTo()[0]->getAddress(),
            array_filter($correos, static fn ($correo): bool => $correo instanceof MimeEmail),
        );

        self::assertContains($nuevo, $destinos);
        self::assertContains($antiguo, $destinos);

        $aviso = $this->emailTo($antiguo);
        self::assertStringContainsString('Si no has sido tú', (string) $aviso->getTextBody());
        self::assertStringNotContainsString($nuevo, (string) $aviso->getTextBody(), 'La dirección nueva va enmascarada.');
    }

    /**
     * `RN-1`: sin la contraseña actual, quien se siente ante una sesión ajena
     * apunta la cuenta a su buzón y se queda con ella.
     */
    public function testWithoutTheCurrentPasswordItIsRefused(): void
    {
        [$antiguo, $token] = $this->activatedPerson('sinclave');

        $this->requestChange($token, $this->address('otro'), 'NoEsEsta9!');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('INCORRECT_PASSWORD', $this->payload()['code']);
        self::assertSame($antiguo, $this->accountEmail($antiguo));
    }

    /**
     * `RN-8`: al confirmar se cierran las sesiones. Sin esto, quien hubiera
     * entrado con una sesión robada seguiría dentro con la cuenta ya
     * apuntando a su buzón.
     */
    public function testConfirmingClosesTheSessions(): void
    {
        [$antiguo, $token] = $this->activatedPerson('sesiones');
        $nuevo = $this->address('sesionesnuevo');
        $otra = $this->logIn($antiguo, self::PASSWORD);
        self::assertNotNull($otra);

        $this->requestChange($token, $nuevo, self::PASSWORD);
        $this->consumePendingEvents();
        $this->confirm($this->confirmationTokenOf($nuevo));

        $this->client->request('POST', '/api/v1/auth/refresh', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['refreshToken' => $otra],
            \JSON_THROW_ON_ERROR,
        ));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * `RN-4`: una dirección ocupada y la que ya tienes responden igual.
     * Decir cuál convertiría esto en un comprobador de quién tiene cuenta.
     */
    public function testATakenAddressIsNotRevealedAsTaken(): void
    {
        [$antiguo, $token] = $this->activatedPerson('quiere');
        [$ajeno] = $this->activatedPerson('ocupado');

        $this->requestChange($token, $ajeno, self::PASSWORD);
        $ocupada = $this->client->getResponse();
        self::assertSame('EMAIL_CHANGE_REFUSED', $this->payload()['code']);

        $this->requestChange($token, $antiguo, self::PASSWORD);
        $propia = $this->client->getResponse();

        self::assertSame($ocupada->getStatusCode(), $propia->getStatusCode());
        self::assertSame($ocupada->getContent(), $propia->getContent());
    }

    /**
     * `RN-6`: solo una solicitud viva. Dos enlaces apuntando a direcciones
     * distintas son una forma de acabar con la cuenta en el buzón
     * equivocado.
     */
    public function testASecondRequestCancelsTheFirst(): void
    {
        [, $token] = $this->activatedPerson('cambiante');
        $primero = $this->address('primerdestino');
        $segundo = $this->address('segundodestino');

        $this->requestChange($token, $primero, self::PASSWORD);
        $this->consumePendingEvents();
        $tokenPrimero = $this->confirmationTokenOf($primero);

        $this->requestChange($token, $segundo, self::PASSWORD);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->consumePendingEvents();
        $tokenSegundo = $this->confirmationTokenOf($segundo);

        $this->confirm($tokenPrimero);
        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
        self::assertSame('EMAIL_CHANGE_LINK_NO_LONGER_VALID', $this->payload()['code']);

        $this->confirm($tokenSegundo);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testTheLinkDoesNotWorkTwice(): void
    {
        [, $token] = $this->activatedPerson('unasolavez');
        $nuevo = $this->address('unasolaveznuevo');

        $this->requestChange($token, $nuevo, self::PASSWORD);
        $this->consumePendingEvents();
        $confirmacion = $this->confirmationTokenOf($nuevo);

        $this->confirm($confirmacion);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->confirm($confirmacion);
        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
    }

    /**
     * `RN-7` y `RN-9`: el nombre de usuario no se re-deriva y la cuenta no
     * vuelve a estar sin activar.
     */
    public function testNeitherTheUsernameNorTheActivationChange(): void
    {
        [$antiguo, $token] = $this->activatedPerson('estable');
        $nuevo = $this->address('establenuevo');

        $usuarioAntes = $this->accountOf($antiguo)->username()->value();

        $this->requestChange($token, $nuevo, self::PASSWORD);
        $this->consumePendingEvents();
        $this->confirm($this->confirmationTokenOf($nuevo));

        $cuenta = $this->accountOf($nuevo);
        self::assertSame($usuarioAntes, $cuenta->username()->value());
        self::assertTrue($cuenta->canWrite(), 'Sigue activada.');
    }

    public function testConfirmingNeedsNoSession(): void
    {
        [, $token] = $this->activatedPerson('otrodispositivo');
        $nuevo = $this->address('otrodispositivonuevo');

        $this->requestChange($token, $nuevo, self::PASSWORD);
        $this->consumePendingEvents();

        // Sin cabecera de autorización, como quien abre el enlace en el móvil.
        $this->confirm($this->confirmationTokenOf($nuevo));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testAnInventedTokenIsRefused(): void
    {
        $this->confirm(str_repeat('b', 64));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('INVALID_EMAIL_CHANGE_TOKEN', $this->payload()['code']);
    }

    private function requestChange(string $token, string $email, string $password): void
    {
        $this->client->request('POST', '/api/v1/me/email-change', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['email' => $email, 'currentPassword' => $password], \JSON_THROW_ON_ERROR));
    }

    private function confirm(string $token): void
    {
        $this->client->request('POST', '/api/v1/me/email-change/confirm', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['token' => $token],
            \JSON_THROW_ON_ERROR,
        ));
    }

    private function confirmationTokenOf(string $newEmail): string
    {
        $correo = $this->emailTo($newEmail);
        self::assertSame(1, preg_match('/token=([0-9a-f]{64})/', (string) $correo->getTextBody(), $matches));

        return $matches[1];
    }

    private function emailTo(string $address): MimeEmail
    {
        foreach (self::getMailerMessages() as $correo) {
            if ($correo instanceof MimeEmail && $correo->getTo()[0]->getAddress() === $address) {
                return $correo;
            }
        }

        self::fail(\sprintf('No ha salido ningún correo hacia %s.', $address));
    }

    private function address(string $local): string
    {
        return \sprintf('%s+%s@ejemplo.com', $local, $this->run);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function activatedPerson(string $local): array
    {
        $email = $this->address($local);

        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => self::PASSWORD,
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->consumePendingEvents();

        $this->client->request('POST', '/api/v1/auth/activate', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['token' => $this->confirmationTokenOf($email)],
            \JSON_THROW_ON_ERROR,
        ));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->logIn($email, self::PASSWORD);

        return [$email, (string) $this->payload()['accessToken']];
    }

    private function accountEmail(string $email): string
    {
        return $this->accountOf($email)->email()->value();
    }

    private function accountOf(string $email): \LectoresBeta\User\Account\Domain\Entity\User
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $user = $users->ofEmail(Email::fromString($email));

        self::assertNotNull($user, \sprintf('No hay cuenta con %s.', $email));

        return $user;
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
