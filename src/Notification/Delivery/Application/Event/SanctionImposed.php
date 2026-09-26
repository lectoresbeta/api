<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `SanctionImposed`, tal y como lo modela `Notification` (`FEAT-MOD-006`
 * `RN-4`).
 *
 * Trae **tipo, motivo y hasta cuándo**, que es exactamente lo que hay que
 * contarle a la persona. Una sanción que no se entiende no corrige nada: solo
 * hace que quien la recibe se vaya.
 *
 * `expiresAt` viene nulo cuando la sanción es indefinida.
 */
final readonly class SanctionImposed implements IncomingIntegrationEvent
{
    private function __construct(
        public string $sanctionId,
        public string $userId,
        public string $type,
        public string $reason,
        public ?\DateTimeImmutable $expiresAt,
        private string $eventId,
        private \DateTimeImmutable $imposedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'SanctionImposed';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $sanctionId = $payload['sanctionId'] ?? null;
        $userId = $payload['userId'] ?? null;
        $type = $payload['type'] ?? null;
        $reason = $payload['reason'] ?? null;
        $expiresAt = $payload['expiresAt'] ?? null;

        if (!\is_string($sanctionId) || !\is_string($userId) || !\is_string($type) || !\is_string($reason)) {
            throw new \InvalidArgumentException('SanctionImposed carries no sanction, person, type or reason.');
        }

        return new self(
            $sanctionId,
            $userId,
            $type,
            $reason,
            \is_string($expiresAt) ? new \DateTimeImmutable($expiresAt) : null,
            $eventId,
            $occurredAt,
        );
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
        return $this->imposedAt;
    }

    public function payload(): array
    {
        return [
            'sanctionId' => $this->sanctionId,
            'userId' => $this->userId,
            'type' => $this->type,
            'reason' => $this->reason,
            'expiresAt' => $this->expiresAt?->format(\DATE_ATOM),
            'imposedAt' => $this->imposedAt->format(\DATE_ATOM),
        ];
    }
}
