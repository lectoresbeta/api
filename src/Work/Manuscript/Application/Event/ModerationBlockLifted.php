<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ModerationBlockLifted`, tal y como lo modela `Work` (`FEAT-MOD-003`
 * `RN-7`).
 *
 * Llega sin instrucciones, igual que `ClaimUpheld`: dice que un moderador ha
 * levantado un bloqueo, y quién cambia el estado de la obra es este contexto.
 */
final readonly class ModerationBlockLifted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $targetType,
        public string $targetId,
        private string $eventId,
        private \DateTimeImmutable $liftedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ModerationBlockLifted';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $targetType = $payload['targetType'] ?? null;
        $targetId = $payload['targetId'] ?? null;

        if (!\is_string($targetType) || !\is_string($targetId)) {
            throw new \InvalidArgumentException('ModerationBlockLifted carries no target.');
        }

        return new self($targetType, $targetId, $eventId, $occurredAt);
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
        return $this->liftedAt;
    }

    public function payload(): array
    {
        return [
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'liftedAt' => $this->liftedAt->format(\DATE_ATOM),
        ];
    }
}
