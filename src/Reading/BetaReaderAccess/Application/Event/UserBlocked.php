<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `UserBlocked`, tal y como lo modela `Reading`.
 *
 * Lo que este contexto hace con él es lo que `FEAT-COM-034` `RN-B1` decide:
 * **el bloqueado pierde el acceso a las obras del bloqueador desde ese
 * instante**. `Community` no lo ordena —ni sabe qué es un acceso de lector
 * beta—: publica el hecho y aquí se decide.
 */
final readonly class UserBlocked implements IncomingIntegrationEvent
{
    private function __construct(
        public string $blockerId,
        public string $blockedId,
        private string $eventId,
        private \DateTimeImmutable $blockedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'UserBlocked';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $blockerId = $payload['blockerId'] ?? null;
        $blockedId = $payload['blockedId'] ?? null;

        if (!\is_string($blockerId) || '' === $blockerId || !\is_string($blockedId) || '' === $blockedId) {
            throw new \InvalidArgumentException('UserBlocked needs both identifiers.');
        }

        return new self($blockerId, $blockedId, $eventId, $occurredAt);
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
        return $this->blockedAt;
    }

    public function payload(): array
    {
        return [
            'blockerId' => $this->blockerId,
            'blockedId' => $this->blockedId,
            'blockedAt' => $this->blockedAt->format(\DATE_ATOM),
        ];
    }
}
