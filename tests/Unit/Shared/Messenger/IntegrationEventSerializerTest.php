<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Shared\Messenger;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Shared\Infrastructure\Messenger\IncomingEventRegistry;
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

        $decoded = $serializer->decode($serializer->encode(new Envelope(new PublishedFact())))->getMessage();

        self::assertInstanceOf(ConsumedFact::class, $decoded);
        self::assertSame('u-1', $decoded->userId);
        self::assertSame('e-1', $decoded->eventId());
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

    public function testANestedPayloadIsRejected(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer()->decode([
            'body' => '{"user":{"id":"u-1"}}',
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
        return new self((string) ($payload['userId'] ?? ''), $eventId, $occurredAt);
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
