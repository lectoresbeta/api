<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `SanctionLifted`, tal y como lo modela `User`: **a quién se le levanta**.
 */
final readonly class SanctionLifted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        private string $eventId,
        private \DateTimeImmutable $liftedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'SanctionLifted';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('SanctionLifted needs a user.');
        }

        return new self($userId, $eventId, $occurredAt);
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
        return ['userId' => $this->userId];
    }
}
