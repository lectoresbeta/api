<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Shared\Messenger;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Shared\Infrastructure\Messenger\IncomingEventRegistry;
use LectoresBeta\Shared\Infrastructure\Messenger\IncomingFacts;
use LectoresBeta\Shared\Infrastructure\Messenger\IntegrationEventSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

/**
 * El formato de cable no lleva nombres de clase, que es lo que permite que
 * `Credits` reaccione a un hecho de `User` sin importar una sola clase suya.
 */
final class IntegrationEventSerializerTest extends TestCase
{
    public function testTheWireFormatCarriesTheFactAndNotTheClass(): void
    {
        $encoded = $this->serializer()->encode(new Envelope(new PublishedFact()));

        self::assertArrayHasKey('headers', $encoded);
        self::assertSame('AccountActivated', $encoded['headers']['X-Event-Name']);
        self::assertSame('{"userId":"u-1"}', $encoded['body']);
        self::assertStringNotContainsString('PublishedFact', json_encode($encoded, \JSON_THROW_ON_ERROR));
    }

    /**
     * Lo que importa: se publica una clase y se recibe **otra**, la del
     * contexto consumidor, sin que ninguna de las dos conozca a la otra.
     */
    public function testAConsumerRebuildsTheFactIntoItsOwnClass(): void
    {
        $serializer = $this->serializer();

        $decoded = $this->decodedBy($serializer, new PublishedFact());

        self::assertCount(1, $decoded);
        self::assertInstanceOf(ConsumedFact::class, $decoded[0]);
        self::assertSame('u-1', $decoded[0]->userId);
        self::assertSame('e-1', $decoded[0]->eventId());
    }

    /**
     * **Un hecho puede tener varios dueños.** `CorrectionStarted` es el
     * primero que los tuvo: `Credits` anota con él el precio de la corrección
     * y `Reading` concede con él el acceso de lector beta, cada uno con su
     * clase.
     *
     * `Messenger` decodifica un mensaje del transporte en **un** objeto, así
     * que las reconstrucciones viajan juntas y se reparten después. Sin esto
     * el segundo contexto no se enteraba de nada, en silencio.
     */
    public function testAFactWithTwoOwnersIsRebuiltOnceForEachOfThem(): void
    {
        $serializer = new IntegrationEventSerializer(
            new IncomingEventRegistry([ConsumedFact::class, OtherConsumedFact::class]),
        );

        $decoded = $this->decodedBy($serializer, new PublishedFact());

        self::assertCount(2, $decoded);
        self::assertInstanceOf(ConsumedFact::class, $decoded[0]);
        self::assertInstanceOf(OtherConsumedFact::class, $decoded[1]);

        // El mismo `eventId` en los dos: cada uno deduplica por su cuenta,
        // con su propio nombre de consumidor.
        self::assertSame('e-1', $decoded[1]->eventId());
    }

    public function testAFactNobodySubscribesToIsRejected(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer()->decode([
            'body' => '{}',
            'headers' => ['X-Event-Name' => 'SomethingElseHappened', 'X-Event-Id' => 'e-1', 'X-Occurred-At' => '2026-09-23T10:00:00+00:00'],
        ]);
    }

    /**
     * Sin `eventId` no hay deduplicación posible, y sin deduplicación un
     * crédito se abona dos veces (`FEAT-CRD-011`). Mejor fallar aquí.
     */
    public function testAFactWithoutAnIdentifierIsRejected(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer()->decode([
            'body' => '{}',
            'headers' => ['X-Event-Name' => 'AccountActivated', 'X-Occurred-At' => '2026-09-23T10:00:00+00:00'],
        ]);
    }

    public function testANestedObjectIsRejected(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer()->decode([
            'body' => '{"user":{"id":"u-1"}}',
            'headers' => ['X-Event-Name' => 'AccountActivated', 'X-Event-Id' => 'e-1', 'X-Occurred-At' => '2026-09-23T10:00:00+00:00'],
        ]);
    }

    /**
     * Una lista de escalares sí pasa: no esconde un agregado y se lee igual
     * de bien en un navegador de colas (`decision:0013`).
     */
    public function testAListOfScalarsSurvivesTheRoundTrip(): void
    {
        $decoded = $this->serializer()->decode([
            'body' => '{"userId":"u-1","genres":["ADVENTURE","DRAMA"]}',
            'headers' => ['X-Event-Name' => 'AccountActivated', 'X-Event-Id' => 'e-1', 'X-Occurred-At' => '2026-09-23T10:00:00+00:00'],
        ])->getMessage();

        self::assertInstanceOf(IncomingFacts::class, $decoded);

        $fact = $decoded->events[0];
        self::assertInstanceOf(ConsumedFact::class, $fact);
        self::assertSame('u-1', $fact->userId);
    }

    public function testAListOfObjectsIsRejected(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer()->decode([
            'body' => '{"genres":[{"code":"DRAMA"}]}',
            'headers' => ['X-Event-Name' => 'AccountActivated', 'X-Event-Id' => 'e-1', 'X-Occurred-At' => '2026-09-23T10:00:00+00:00'],
        ]);
    }

    public function testAClassThatIsNotAnIncomingEventIsRefusedAtStartup(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new IncomingEventRegistry([\stdClass::class]);
    }

    private function serializer(): IntegrationEventSerializer
    {
        return new IntegrationEventSerializer(new IncomingEventRegistry([ConsumedFact::class]));
    }

    /**
     * @return list<IncomingIntegrationEvent>
     */
    private function decodedBy(IntegrationEventSerializer $serializer, IntegrationEvent $published): array
    {
        $decoded = $serializer->decode($serializer->encode(new Envelope($published)))->getMessage();

        self::assertInstanceOf(IncomingFacts::class, $decoded);

        return $decoded->events;
    }
}

/**
 * El hecho tal y como lo publica su contexto de origen.
 */
final class PublishedFact implements IntegrationEvent
{
    public function eventId(): string
    {
        return 'e-1';
    }

    public function eventName(): string
    {
        return 'AccountActivated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-23T10:00:00+00:00');
    }

    public function payload(): array
    {
        return ['userId' => 'u-1'];
    }
}

/**
 * El mismo hecho tal y como lo modela quien lo consume: otra clase, en otro
 * contexto, que solo declara el campo que necesita.
 */
final class ConsumedFact implements IncomingIntegrationEvent
{
    private function __construct(
        public readonly string $userId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'AccountActivated';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;

        return new self(\is_string($userId) ? $userId : '', $eventId, $occurredAt);
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function eventName(): string
    {
        return self::subscribesTo();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return ['userId' => $this->userId];
    }
}

/**
 * Otro contexto escuchando el mismo hecho, con su propia clase.
 */
final class OtherConsumedFact implements IncomingIntegrationEvent
{
    private function __construct(
        public readonly string $userId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'AccountActivated';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;

        return new self(\is_string($userId) ? $userId : '', $eventId, $occurredAt);
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function eventName(): string
    {
        return self::subscribesTo();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return ['userId' => $this->userId];
    }
}
