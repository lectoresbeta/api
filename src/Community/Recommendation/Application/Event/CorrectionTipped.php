<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CorrectionTipped`, tal y como lo modela `Community`: **quién recibió y
 * cuánto** (`FEAT-CRD-017`).
 *
 * Es lo que permite que la propina alimente la reputación del corrector **sin
 * que `Credits` sepa nada de rankings**, y es la señal de calidad más fiable
 * que tiene la plataforma: la única que alguien ha pagado de su bolsillo, y
 * por eso mucho más difícil de falsear que un «me gusta».
 */
final readonly class CorrectionTipped implements IncomingIntegrationEvent
{
    private function __construct(
        public string $readerId,
        public int $amount,
        private string $eventId,
        private \DateTimeImmutable $tippedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CorrectionTipped';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $readerId = $payload['readerId'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (!\is_string($readerId) || '' === $readerId || !\is_int($amount)) {
            throw new \InvalidArgumentException('CorrectionTipped needs a reader and an amount.');
        }

        return new self($readerId, $amount, $eventId, $occurredAt);
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
        return $this->tippedAt;
    }

    public function payload(): array
    {
        return ['readerId' => $this->readerId, 'amount' => $this->amount];
    }
}
