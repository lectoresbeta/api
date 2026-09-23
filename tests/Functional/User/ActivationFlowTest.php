<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\User;

use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId as CreditsUserId;
use LectoresBeta\Shared\Infrastructure\Messenger\IntegrationEventSerializer;
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
 * La rodaja entera, de alta a saldo, pasando de verdad por el formato de
 * cable: registro → correo de activación → activación → 10 créditos.
 *
 * Cada evento se **codifica y se vuelve a decodificar** con el serializador
 * real antes de entregarlo. Es lo que da valor al test: comprueba que un
 * contexto reconstruye el hecho de otro sin compartir una sola clase
 * (`decision:0013`), que es la afirmación central de la arquitectura y la que
 * no se puede comprobar llamando al handler a mano.
 */
final class ActivationFlowTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
    }

    public function testFromSignUpToWelcomeCredits(): void
    {
        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'recorrido@ejemplo.com',
            'password' => 'Valida1!',
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        // 1. `Notification` recibe el hecho y manda el correo.
        $this->consumePendingEvents();

        $emails = self::getMailerMessages();
        self::assertCount(1, $emails);

        $email = $emails[0];
        self::assertInstanceOf(MimeEmail::class, $email);
        self::assertSame('recorrido@ejemplo.com', $email->getTo()[0]->getAddress());

        $token = $this->tokenFromEmailBody((string) $email->getTextBody());

        // El token va en el correo y **en ningún otro sitio**: ni en el
        // payload del aviso guardado, ni en el evento publicado (`RN-5`).
        self::assertStringNotContainsString($token, json_encode($this->publishedPayloads(), \JSON_THROW_ON_ERROR));

        // 2. La persona sigue el enlace.
        $this->client->request('POST', '/api/v1/auth/activate', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['token' => $token],
            \JSON_THROW_ON_ERROR,
        ));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $user = $this->users()->ofEmail(Email::fromString('recorrido@ejemplo.com'));
        self::assertNotNull($user);
        self::assertSame(AccountStatus::ACTIVE, $user->status());

        // 3. `Credits` lo interpreta por su cuenta.
        self::assertNull($this->creditsOf($user->id()->value()), 'Credits no debe saber nada hasta la activación.');

        $this->consumePendingEvents();

        self::assertSame(10, $this->creditsOf($user->id()->value()));
    }

    /**
     * Una reentrega del mismo hecho no manda un segundo correo
     * (`FEAT-NOT-008` `RN-2`). Y eso importa más de lo que parece: un correo
     * repetido trae un token nuevo que **invalida el del primero**, así que el
     * enlace que la persona ya tenía abierto dejaría de funcionar.
     */
    public function testARedeliveredRegistrationDoesNotSendASecondEmail(): void
    {
        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'repetido@ejemplo.com',
            'password' => 'Valida1!',
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));

        $encoded = $this->pendingEncodedEvents();

        $this->deliver($encoded);
        $this->deliver($encoded);

        self::assertCount(1, self::getMailerMessages());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publishedPayloads(): array
    {
        return array_map(
            static function (array $encoded): array {
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode((string) $encoded['body'], true, 512, \JSON_THROW_ON_ERROR);

                return $decoded;
            },
            $this->pendingEncodedEvents(),
        );
    }

    /**
     * Lo que está esperando en la cola, ya en formato de cable.
     *
     * @return list<array{body: string, headers: array<string, string>}>
     */
    private function pendingEncodedEvents(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.integration');
        $serializer = $this->serializer();

        $encoded = [];

        foreach ($transport->getSent() as $envelope) {
            /** @var array{body: string, headers: array<string, string>} $wire */
            $wire = $serializer->encode($envelope);
            $encoded[] = $wire;
        }

        return $encoded;
    }

    private function consumePendingEvents(): void
    {
        $this->deliver($this->pendingEncodedEvents());
    }

    /**
     * @param list<array{body: string, headers: array<string, string>}> $encoded
     */
    private function deliver(array $encoded): void
    {
        /** @var MessageBusInterface $bus */
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $serializer = $this->serializer();

        foreach ($encoded as $wire) {
            $envelope = $serializer->decode($wire);

            // `ReceivedStamp` es lo que le dice a Messenger que este mensaje
            // viene del transporte y toca ejecutarlo aquí, en vez de volver a
            // encolarlo.
            $bus->dispatch($envelope->with(new ReceivedStamp('integration')));
        }
    }

    /**
     * El serializador **tal y como corre en producción**: Messenger envuelve
     * el nuestro en uno que firma, y es esa pila entera la que interesa
     * ejercitar.
     */
    private function serializer(): SerializerInterface
    {
        /** @var SerializerInterface $serializer */
        $serializer = self::getContainer()->get(IntegrationEventSerializer::class);

        return $serializer;
    }

    private function tokenFromEmailBody(string $body): string
    {
        self::assertSame(1, preg_match('/token=([0-9a-f]{64})/', $body, $matches), 'El correo no lleva un enlace de activación.');

        return $matches[1];
    }

    private function creditsOf(string $userId): ?int
    {
        /** @var CreditAccountRepository $accounts */
        $accounts = self::getContainer()->get(CreditAccountRepository::class);

        return $accounts->ofUser(CreditsUserId::fromString($userId))?->balance();
    }

    private function users(): UserRepository
    {
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);

        return $users;
    }
}
