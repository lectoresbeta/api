<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CorrectionTipped`, tal y como lo modela `Feedback`.
 *
 * `Credits` cuenta lo que ha pasado y este contexto lo apunta en la
 * corrección, que es donde autor y corrector lo van a ver. Es la misma
 * dirección que `CreditsAdded`: **`Credits` narra, nadie le pregunta**.
 */
final readonly class CorrectionTipped implements IncomingIntegrationEvent
{
    private function __construct(
        public string $correctionId,
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
        $correctionId = $payload['correctionId'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (!\is_string($correctionId) || '' === $correctionId || !\is_int($amount)) {
            throw new \InvalidArgumentException('CorrectionTipped needs a correction and an amount.');
        }

        return new self($correctionId, $amount, $eventId, $occurredAt);
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
        return ['correctionId' => $this->correctionId, 'amount' => $this->amount];
    }
}
