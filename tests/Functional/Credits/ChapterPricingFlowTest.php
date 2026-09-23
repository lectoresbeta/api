<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Functional\Credits;

use Doctrine\Persistence\ManagerRegistry;
use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Infrastructure\Messenger\IntegrationEventSerializer;
use LectoresBeta\User\Account\Domain\Entity\AccountActivationToken;
use LectoresBeta\User\Account\Domain\Repository\AccountActivationTokenRepository;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\AccountActivationTokenId;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * El precio de una corrección, de punta a punta (`FEAT-CRD-016`).
 *
 * Lo que este test defiende no es la aritmética —eso ya lo hace
 * `ChapterPricingTest`— sino **la frontera**: `Work` publica cuánto mide un
 * capítulo y cuánto exige un cuestionario, y `Credits` decide por su cuenta
 * lo que eso vale. Ninguna de las dos mitades importa una clase de la otra.
 *
 * Cada evento se codifica y se vuelve a decodificar con el serializador real
 * antes de entregarlo, que es lo único que demuestra que los dos lados se
 * entienden sin compartir modelo (`decision:0013`).
 */
final class ChapterPricingFlowTest extends WebTestCase
{
    private const PASSWORD = 'Valida1!';

    /**
     * Los hechos que le importan al precio. Se filtran aquí por lo mismo que
     * en producción: una cola entrega a cada consumidor lo que su binding
     * dice, no todo lo que se publica.
     */
    private const SUBSCRIBED = ['ChapterContentUpdated', 'QuestionnaireUpdated'];

    private KernelBrowser $client;
    private string $run;

    /**
     * La cola, tal y como la ve el consumidor: hechos ya serializados, que se
     * acumulan hasta que alguien los entrega. Hay que recogerlos justo tras
     * la petición que los publica — el transporte en memoria se vacía con
     * cada una.
     *
     * @var list<array{body: string, headers: array<string, string>}>
     */
    private array $queue = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->run = bin2hex(random_bytes(4));
    }

    /**
     * Los dos términos de la fórmula, cada uno llegando de un hecho distinto:
     * la longitud del capítulo y lo que el cuestionario exige.
     */
    public function testTheTwoFactsOfAPriceArriveSeparatelyAndMeetInCredits(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);
        $chapterId = $this->addChapter($workId, $token, words: 1200);

        $this->consumeEverything();

        // Todavía sin cuestionario: solo se paga por leer, con el suelo de 2.
        self::assertSame(2, $this->priceOf($chapterId));

        $this->saveQuestionnaire($workId, $token, [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 100],
            ['statement' => '¿Y el desenlace?', 'minWords' => 150, 'scope' => 'LAST_CHAPTER'],
        ]);

        $this->consumeEverything();

        // 2 de lectura (1.200 palabras) + 3 de escritura (250 exigidas):
        // es el único capítulo, así que responde también la del desenlace.
        self::assertSame(5, $this->priceOf($chapterId));
    }

    /**
     * `W-17` visto desde `Credits`: el autor no paga en el capítulo uno una
     * pregunta sobre el final. Y cuando aparece un capítulo nuevo, el que era
     * último deja de cobrarla.
     */
    public function testANewChapterMovesWhereTheWorkEnds(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);

        $this->saveQuestionnaire($workId, $token, [
            ['statement' => '¿Cómo funciona el ritmo?', 'minWords' => 100],
            ['statement' => '¿Y el desenlace?', 'minWords' => 150, 'scope' => 'LAST_CHAPTER'],
        ]);

        $primero = $this->addChapter($workId, $token, words: 1200);
        $this->consumeEverything();

        self::assertSame(5, $this->priceOf($primero), 'Es el último de la obra: responde a todo.');

        $segundo = $this->addChapter($workId, $token, words: 1200);
        $this->consumeEverything();

        self::assertSame(3, $this->priceOf($primero), '2 de lectura + 1 de escritura: ya no es el último.');
        self::assertSame(5, $this->priceOf($segundo));
    }

    /**
     * La cola no promete entrega única ni orden. Reentregar lo mismo no puede
     * mover un precio, y una versión vieja que llega tarde no puede deshacer
     * lo que el autor ya cambió.
     */
    public function testARedeliveryChangesNothingAndAStaleVersionIsIgnored(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);
        $chapterId = $this->addChapter($workId, $token, words: 1200);

        $primeraVersion = $this->saveQuestionnaire($workId, $token, [['statement' => 'Una', 'minWords' => 100]]);

        $this->saveQuestionnaire($workId, $token, [['statement' => 'Una', 'minWords' => 900]]);

        $this->consumeEverything();

        // 2 de lectura + 9 de escritura.
        self::assertSame(11, $this->priceOf($chapterId));

        // La versión 1, que llega tarde y con menos exigencia.
        $this->deliver($primeraVersion);
        self::assertSame(11, $this->priceOf($chapterId), 'Una versión vieja no rebaja el precio.');

        // Y el mismo hecho otra vez, entero.
        $this->consumeEverything();
        self::assertSame(11, $this->priceOf($chapterId));
    }

    /**
     * Lo que **no** viaja: ni una palabra del manuscrito, ni un importe.
     * `Work` publica un hecho; quien pone precio es `Credits` (`RN-1`).
     */
    public function testTheFactThatCrossesCarriesNoTextAndNoAmount(): void
    {
        $token = $this->author('autora');
        $workId = $this->createWork($token);
        $this->addChapter($workId, $token, words: 1200, marker: 'Ryn cruzó el puente');

        $wire = array_values(array_filter(
            $this->queue,
            static fn (array $event): bool => 'ChapterContentUpdated' === ($event['headers']['X-Event-Name'] ?? null),
        ));

        self::assertCount(1, $wire);

        $body = $wire[0]['body'];

        self::assertStringNotContainsString('Ryn', $body);
        self::assertStringNotContainsString('puente', $body);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(1200, $payload['wordCount']);
        self::assertSame(1, $payload['position']);
        self::assertArrayNotHasKey('price', $payload);
        self::assertArrayNotHasKey('credits', $payload);
    }

    private function priceOf(string $chapterId): ?int
    {
        /** @var ChapterPriceRepository $prices */
        $prices = self::getContainer()->get(ChapterPriceRepository::class);

        return $prices->ofChapter(ChapterId::fromString($chapterId))?->price();
    }

    private function addChapter(string $workId, string $token, int $words, string $marker = 'Capítulo'): string
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
    private function saveQuestionnaire(string $workId, string $token, array $questions): array
    {
        $this->client->request('PUT', \sprintf('/api/v1/works/%s/questionnaire', $workId), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['questions' => $questions], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        return $this->capture('QuestionnaireUpdated');
    }

    /**
     * Recoge lo que acaba de publicarse y lo encola. Se llama inmediatamente
     * después de cada petición: el transporte en memoria conserva solo los
     * mensajes de la última.
     *
     * @return list<array{body: string, headers: array<string, string>}> lo recogido en esta llamada
     */
    private function capture(?string $name = null): array
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
     * Entrega todo lo encolado. La cola no se vacía: volver a llamar reentrega
     * los mismos hechos, que es exactamente lo que RabbitMQ puede hacer.
     */
    private function consumeEverything(): void
    {
        $this->deliver($this->queue);
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
            $bus->dispatch($envelope->with(new ReceivedStamp('integration')));
        }

        /** @var ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        $doctrine->getManager()->clear();
    }

    private function serializer(): SerializerInterface
    {
        /** @var SerializerInterface $serializer */
        $serializer = self::getContainer()->get(IntegrationEventSerializer::class);

        return $serializer;
    }

    private function createWork(string $token): string
    {
        $this->client->request('POST', '/api/v1/works', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: json_encode(['title' => 'La ciudad de los pájaros'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->capture();

        return (string) $this->payload()['workId'];
    }

    private function address(string $local): string
    {
        return \sprintf('%s+%s@ejemplo.com', $local, $this->run);
    }

    private function author(string $local): string
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

        return $token;
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
