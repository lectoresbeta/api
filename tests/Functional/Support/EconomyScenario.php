<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Support;

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
 * Lo que comparten las pruebas del ciclo económico —`Credits` y `Feedback`—: una persona con la cuenta
 * activada, una obra con capítulos, y **la cola**.
 *
 * Cada hecho se codifica y se vuelve a decodificar con el serializador real
 * antes de entregarlo. Es lo único que demuestra que dos contextos se
 * entienden sin compartir una sola clase
 * ([`decision:0013`](../../../docs/decisions/0013-integration-events-travel-without-class-names.md)),
 * que es la afirmación central de la arquitectura.
 */
abstract class EconomyScenario extends WebTestCase
{
    protected const PASSWORD = 'Valida1!';

    /**
     * A qué reacciona la aplicación. Se filtra aquí por lo mismo que en
     * producción: una cola entrega a cada consumidor lo que dice su binding,
     * no todo lo que se publica.
     */
    private const SUBSCRIBED = [
        'AccountActivated',
        'ChapterContentUpdated',
        'QuestionnaireUpdated',
        'CorrectionStarted',
        'CorrectionResumed',
        'CorrectionDraftDiscarded',
        'FeedbackSubmitted',
        'ChapterCorrectabilityChanged',
        'ChapterPriceChanged',
        'CreditBalanceChanged',

        // Lo que `User` proyecta para resolver las audiencias `FOLLOWERS`
        // (`FEAT-COM-010`). Las dos mitades: sin la segunda, quien deja de
        // seguir seguiría contando como seguidor.
        'AuthorSubscribed',
        'AuthorUnsubscribed',

        // Y lo que un bloqueo significa en cada contexto (`FEAT-COM-034`):
        // `User` deja de aceptar comentarios entre los dos y `Reading` retira
        // el acceso a las obras del bloqueador.
        'UserBlocked',
        'UserUnblocked',

        // Y lo que `Notification` convierte en avisos (`FEAT-NOT-001`).
        // `FeedbackSubmitted` y `UserBlocked` ya estaban arriba: el mismo
        // hecho lo escuchan varios contextos, cada uno con su clase.
        'AccessRequested',
        'AccessRequestRejected',
        'BetaReaderAccessGranted',
        'BetaReaderAccessRevoked',
        'BetaReaderInvited',

        // Y los dos cruces del descubierto (`FEAT-CRD-018`): `Feedback`
        // bloquea y libera la lectura de lo entregado.
        'CreditBalanceWentNegative',
        'CreditDebtCleared',

        // Y lo que una reclamación estimada desencadena (`FEAT-MOD-002`,
        // `FEAT-MOD-003`): `Work` bloquea lo reclamado y `Credits` revierte
        // lo que se cobró; el bloqueo, a su vez, avisa al autor.
        'ClaimUpheld',
        'WorkBlockedByModeration',

        // Y el aviso que se retira solo cuando el autor abre lo que recibió
        // (`FEAT-FBK-004`).
        'CorrectionRead',

        // Y lo que el autor hace con lo que recibe (`FEAT-FBK-005`,
        // `FEAT-FBK-006`), que es lo único que la plataforma le devuelve a
        // quien corrigió.
        'FeedbackReplied',
        'FeedbackRatedPositively',

        // Y el eco de lo que se pagó por cada corrección (`FEAT-FBK-010`).
        'CreditsAdded',
        'CreditsSpent',

        // Y lo que mover o quitar un capítulo significa para el precio
        // (`FEAT-WRK-003`).
        'ChaptersReordered',
        'ChapterRemoved',

        // Y lo que retirar una obra significa para quien la estaba leyendo
        // (`FEAT-WRK-006`).
        'WorkArchived',
    ];

    /**
     * Un hecho provoca otro, y ese otro puede provocar un tercero. Más allá
     * de esto es un ciclo, y conviene que el test lo diga en vez de colgarse.
     */
    private const MAX_CASCADE_ROUNDS = 6;

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

    /**
     * Identificadores ya encolados. El transporte en memoria conserva lo
     * enviado hasta que el kernel lo reinicia, así que sin esto un hecho se
     * encolaría de nuevo cada vez que se mira.
     *
     * @var array<string, true>
     */
    private array $seen = [];

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

            $eventId = $wire['headers']['X-Event-Id'] ?? '';

            if (isset($this->seen[$eventId])) {
                continue;
            }

            $this->seen[$eventId] = true;
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
     * Todo lo encolado de ese tipo, se haya recogido cuando se haya recogido.
     *
     * @return list<array{body: string, headers: array<string, string>}>
     */
    protected function queued(string $eventName): array
    {
        return array_values(array_filter(
            $this->queue,
            static fn (array $wire): bool => ($wire['headers']['X-Event-Name'] ?? null) === $eventName,
        ));
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
     * Entrega todo lo encolado, **y lo que aparezca al entregarlo**.
     *
     * Un consumidor publica: `Credits` cobra una corrección y anuncia que el
     * saldo cambió, lo que cambia qué capítulos son corregibles, lo que el
     * catálogo proyecta. En producción eso lo hace el broker sin que nadie
     * lo piense; aquí hay que recoger la cascada a mano.
     *
     * La cola no se vacía, y **cada ronda la entrega entera**: un hecho se
     * reentrega muchas veces en una sola llamada, y muchas más al volver a
     * llamar. Es lo más duro que se le puede pedir a un consumidor, y es a
     * propósito — RabbitMQ no promete entrega única, así que la idempotencia
     * de todos ellos es requisito y no cortesía.
     *
     * Esto llegó a suavizarse, entregando por ronda solo lo nuevo, cuando
     * conectar `Notification` hizo visible una oscilación entre conceder
     * acceso al corregir y revocarlo. Era el síntoma de un fallo real —una
     * reentrega deshacía una revocación— y se arregló donde estaba (`R-22`),
     * así que la exigencia vuelve a estar entera. Suavizar esto habría
     * escondido justo lo que encontró.
     */
    protected function consumeEverything(): void
    {
        for ($round = 0; $round < self::MAX_CASCADE_ROUNDS; ++$round) {
            $this->deliver($this->queue);

            $pending = \count($this->queue);
            $this->capture();

            if (\count($this->queue) === $pending) {
                return;
            }
        }

        self::fail('Los hechos no dejan de producir hechos: hay un ciclo en la cascada.');
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

    /**
     * Alguien que ha iniciado sesión pero **no ha activado la cuenta**, que
     * es lo que separa leer de escribir (`FEAT-USR-025`).
     */
    protected function signedInWithoutActivating(string $local): string
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
        $this->capture();

        return (string) $this->payload()['accessToken'];
    }

    protected function address(string $local): string
    {
        return \sprintf('%s+%s@ejemplo.com', $local, $this->run);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param array{token: string, userId: string} $autora
     */
    protected function publishedWork(array $autora, string $title = 'La obra reclamada'): string
    {
        $workId = $this->createWork($autora['token'], $title);
        $this->addChapter($workId, $autora['token'], words: 900);
        // `PUBLIC` y no el `ON_REQUEST` por defecto: una obra que un extraño
        // no puede abrir de todos modos no sirve para comprobar que dejó de
        // poder abrirla.
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->consumeEverything();

        return $workId;
    }

    /**
     * Una corrección entregada y pagada: el único camino por el que los
     * créditos se mueven de verdad, y por tanto el único desde el que se
     * puede comprobar que una reversión los devuelve.
     *
     * @return array{0: array{token: string, userId: string}, 1: array{token: string, userId: string}, 2: string, 3: string}
     */
    protected function aDeliveredCorrection(): array
    {
        $autora = $this->activatedPerson('autora');
        $lectora = $this->activatedPerson('lectora');

        $workId = $this->createWork($autora['token'], 'La obra corregida');
        $chapterId = $this->addChapter($workId, $autora['token'], words: 900);
        $this->saveQuestionnaire($workId, $autora['token'], [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 10],
        ]);
        $this->putAs(\sprintf('/api/v1/works/%s/access-mode', $workId), $autora['token'], ['accessMode' => 'PUBLIC']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'PUBLISHED']);
        $this->putAs(\sprintf('/api/v1/works/%s/status', $workId), $autora['token'], ['status' => 'IN_CORRECTION']);
        $this->consumeEverything();

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections/start', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();
        $this->consumeEverything();

        $this->client->request('GET', \sprintf('/api/v1/chapters/%s/questionnaire', $chapterId), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ]);
        /** @var list<array{questionId: string}> $questions */
        $questions = $this->payload()['questions'];

        $this->client->request('POST', \sprintf('/api/v1/chapters/%s/corrections', $chapterId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$lectora['token'],
        ], content: json_encode([
            'answers' => array_map(
                static fn (array $question): array => [
                    'questionId' => $question['questionId'],
                    'text' => implode(' ', array_fill(0, 60, 'palabra')),
                ],
                $questions,
            ),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $correctionId = (string) $this->payload()['correctionId'];
        $this->capture();
        $this->consumeEverything();

        return [$autora, $lectora, $correctionId, $workId];
    }

    /**
     * Un `PUT` con sesión que además recoge lo que la petición publique.
     *
     * Se llama `putAs` y no `put` porque varias pruebas traen el suyo
     * propio, privado, y un nombre compartido los convertiría en
     * sobrescrituras accidentales.
     *
     * @param array<string, mixed> $body
     */
    protected function putAs(string $path, string $token, array $body): void
    {
        $this->client->request('PUT', $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $this->capture();
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
