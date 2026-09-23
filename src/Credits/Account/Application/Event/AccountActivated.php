<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `AccountActivated`, as `Credits` models it.
 *
 * A **different class** from the one `User` publishes, on purpose
 * ([`decision:0013`](../../../../../docs/decisions/0013-integration-events-travel-without-class-names.md)).
 * Nothing here imports anything from `User`, and nothing in `User` knows this
 * exists. What the two share is the name of the fact and the shape of its
 * payload, which the event catalogue documents.
 *
 * Note what it does **not** carry: an amount. `User` publishes that an
 * account was activated; deciding that this is worth 10 credits belongs here
 * (`FEAT-CRD-002`).
 */
final readonly class AccountActivated implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        private string $eventId,
        private \DateTimeImmutable $activatedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'AccountActivated';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('AccountActivated carries no userId.');
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
        return $this->activatedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'activatedAt' => $this->activatedAt->format(\DATE_ATOM),
        ];
    }
}
