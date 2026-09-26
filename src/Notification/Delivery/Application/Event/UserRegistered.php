<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `UserRegistered`, as `Notification` models it (`decision:0013`).
 *
 * It declares only `userId`, although the publisher also sends the address
 * and the username. Not an oversight: the address that matters is the one
 * that is current **when the email goes out**, and that comes back with the
 * activation link. A copy taken at registration would be stale the day
 * somebody changes their email before activating.
 */
final readonly class UserRegistered implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        private string $eventId,
        private \DateTimeImmutable $registeredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'UserRegistered';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('UserRegistered carries no userId.');
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
        return $this->registeredAt;
    }

    public function payload(): array
    {
        return ['userId' => $this->userId];
    }
}
