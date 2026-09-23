<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Event;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Somebody's balance is now a different number (`FEAT-CRD-006`).
 *
 * The one balance event that is not about **why**. `CreditsAdded` and
 * `CreditsSpent` narrate a movement, for whoever wants to tell the person
 * about it; this one just states the current figure, for the read models
 * that need to keep up — the catalogue's ordering among them
 * ([`decision:0008`](../../../../../docs/decisions/0008-catalogue-ordering.md)).
 */
final readonly class CreditBalanceChanged implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private int $balance,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CreditBalanceChanged';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'balance' => $this->balance,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
