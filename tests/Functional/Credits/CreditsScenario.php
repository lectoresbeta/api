<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Credits;

use Doctrine\Persistence\ManagerRegistry;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId as CreditsUserId;
use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Shared\Infrastructure\Messenger\IntegrationEventSerializer;
use LectoresBeta\User\Account\Domain\Entity\AccountActivationToken;
use LectoresBeta\User\Account\Domain\Repository\AccountActivationTokenRepository;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\AccountActivationTokenId;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Lo que comparten las pruebas de la economía: una persona con la cuenta
 * activada, una obra con capítulos, y **la cola**.
 *
 * Cada hecho se codifica y se vuelve a decodificar con el serializador real
 * antes de entregarlo. Es lo único que demuestra que dos contextos se
 * entienden sin compartir una sola clase
 * ([`decision:0013`](../../../docs/decisions/0013-integration-events-travel-without-class-names.md)),
 * que es la afirmación central de la arquitectura.
 */
abstract class CreditsScenario extends WebTestCase
{
    protected const PASSWORD = 'Valida1!';

    /**
     * A qué reacciona `Credits`. Se filtra aquí por lo mismo que en
     * producción: una cola entrega a cada consumidor lo que dice su binding,
     * no todo lo que se publica.
     */
    private const SUBSCRIBED = [
        'AccountActivated',
        'ChapterContentUpdated',
        'QuestionnaireUpdated',
        'CorrectionStarted',
        'CorrectionDraftDiscarded',
        'FeedbackSubmitted',
    ];

    protected KernelBrowser $client;

    protected string $run;

    /**
     * La cola, tal y como la ve el consumidor: hechos ya serializados que se
     * acumulan hasta que alguien los entrega. Hay que recogerlos justo tras
     * la petición que los publica — el transporte en memoria se vacía con
     * cada una.
     *
     * @var list<array{body: string, headers: array<string, string>}>
     */
    protected array $queue = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->run = bin2hex(random_bytes(4));
    }

    /**
     * Recoge lo publicado por la última petición y lo encola.
     *
     * @return list<array{body: string, headers: array<string, string>}> lo recogido en esta llamada
     */
    protected function capture(?string $name = null): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.integration');
        $serializer = $this->serializer();

        $captured = [];

        foreach ($transport->getSent() as $envelope) {
            /** @var array{body: string, headers: array<string, string>} $wire */
            $wire = $serializer->encode($envelope);
            $published = $wire['headers']['X-Event-Name'] ?? null;

            if (!\in_array($published, self::SUBSCRIBED, true)) {
                continue;
            }

            $this->queue[] = $wire;

            if (null === $name || $published === $name) {
                $captured[] = $wire;
            }
        }

        return $captured;
    }

    /**
     * Lo que `Credits` ha publicado, sin pasar por la cola: nadie se suscribe
     * todavía a sus hechos, así que se leen del transporte.
     *
     * @return list<array<string, mixed>>
     */
    protected function announced(string $eventName): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.integration');

        $payloads = [];

        foreach ($transport->getSent() as $envelope) {
            $event = $envelope->getMessage();

            if ($event instanceof IntegrationEvent && $event->eventName() === $eventName) {
                $payloads[] = $event->payload();
            }
        }

        return $payloads;
    }

    /**
     * El último aviso de ese tipo, que es el que refleja el estado actual.
     *
     * @return array<string, mixed>
     */
    protected function lastAnnouncementOf(string $eventName): array
    {
        $announcements = $this->announced($eventName);

        self::assertNotEmpty($announcements, \sprintf('No se ha publicado ningún %s.', $eventName));

        return $announcements[\count($announcements) - 1];
    }

    /**
     * Entrega todo lo encolado. La cola no se vacía: volver a llamar
     * reentrega los mismos hechos, que es lo que RabbitMQ puede hacer.
     */
    protected function consumeEverything(): void
    {
        $this->deliver($this->queue);
    }

    /**
     * Pone un hecho en la cola como si lo hubiera publicado su contexto.
     *
     * `Feedback` todavía no existe como código, así que sus hechos se
     * fabrican aquí —**con el payload que el catálogo de eventos define**— y
     * viajan por el serializador real. El día que ese contexto los publique,
     * lo único que tiene que coincidir es eso: el nombre y la forma.
     */
    protected function enqueue(IntegrationEvent $fact): void
    {
        $wire = $this->serializer()->encode(new Envelope($fact));

        /** @var array{body: string, headers: array<string, string>} $wire */
        $this->queue[] = $wire;
    }

    protected function eventId(): string
    {
        return EventId::generate()->value();
    }

    /**
     * @param list<array{body: string, headers: array<string, string>}> $encoded
     */
    protected function deliver(array $encoded): void
    {
        /** @var MessageBusInterface $bus */
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $serializer = $this->serializer();

        foreach ($encoded as $wire) {
            $envelope = $serializer->decode($wire);
            $bus->dispatch($envelope->with(new ReceivedStamp('integration')));
        }

        /** @var ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        $doctrine->getManager()->clear();
    }

    protected function serializer(): SerializerInterface
    {
        /** @var SerializerInterface $serializer */
        $serializer = self::getContainer()->get(IntegrationEventSerializer::class);

        return $serializer;
    }

    protected function balanceOf(string $userId): ?int
    {
        /** @var CreditAccountRepository $accounts */
        $accounts = self::getContainer()->get(CreditAccountRepository::class);

        return $accounts->ofUser(CreditsUserId::fromString($userId))?->balance();
    }

    protected function createWork(string $token, string $title = 'La ciudad de los pájaros'): string
    {
        $this->client->request('POST', '/api/v1/works', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['title' => $title], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['workId'];
    }

    protected function addChapter(string $workId, string $token, int $words, string $marker = 'Capítulo'): string
    {
        $opening = explode(' ', $marker);
        $filler = array_fill(0, $words - \count($opening), 'palabra');
        $content = '<p>'.implode(' ', [...$opening, ...$filler]).'</p>';

        $this->client->request('POST', \sprintf('/api/v1/works/%s/chapters', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['content' => $content], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['chapterId'];
    }

    /**
     * @param list<array<string, mixed>> $questions
     *
     * @return list<array{body: string, headers: array<string, string>}> el hecho que acaba de publicarse
     */
    protected function saveQuestionnaire(string $workId, string $token, array $questions): array
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/questionnaire', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['questions' => $questions], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        return $this->capture('QuestionnaireUpdated');
    }

    /**
     * Alguien con la cuenta activada, que es lo que hace falta para escribir
     * y lo que le abona los diez créditos de bienvenida.
     *
     * @return array{token: string, userId: string}
     */
    protected function activatedPerson(string $local): array
    {
        $email = $this->address($local);

        $this->client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => self::PASSWORD,
            'acceptedLegalVersions' => ['termsOfUse' => '2026-01-15', 'privacyPolicy' => '2026-01-15'],
        ], \JSON_THROW_ON_ERROR));

        $this->client->request('POST', '/api/v1/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => \sprintf('198.51.%d.%d', random_int(0, 255), random_int(1, 254)),
        ], content: json_encode(['email' => $email, 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        $token = (string) $this->payload()['accessToken'];

        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $user = $users->ofEmail(Email::fromString($email));
        self::assertNotNull($user);

        /** @var SecureTokenFactory $factory */
        $factory = self::getContainer()->get(SecureTokenFactory::class);
        $secret = $factory->create();

        /** @var AccountActivationTokenRepository $tokens */
        $tokens = self::getContainer()->get(AccountActivationTokenRepository::class);
        $tokens->save(new AccountActivationToken(
            AccountActivationTokenId::generate(),
            $user->id(),
            $secret->hash,
            new \DateTimeImmutable(),
            new \DateTimeImmutable('+2 days'),
        ));

        /** @var ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        $doctrine->getManager()->flush();

        $this->client->request('POST', '/api/v1/auth/activate', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['token' => $secret->plain],
            \JSON_THROW_ON_ERROR,
        ));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->capture();

        return ['token' => $token, 'userId' => $user->id()->value()];
    }

    protected function address(string $local): string
    {
        return \sprintf('%s+%s@ejemplo.com', $local, $this->run);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
