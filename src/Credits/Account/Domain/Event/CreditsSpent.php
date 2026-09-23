<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Event;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Credits left an account (`FEAT-CRD-006`).
 *
 * `amount` is **positive**: it is how much was spent, not a signed movement.
 * The sign lives in `credit_transaction`, where a sum has to come out as a
 * balance; an event is read by people, and «gastaste −6 créditos» is a
 * sentence nobody wants to write.
 */
final readonly class CreditsSpent implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private int $amount,
        private string $reason,
        private int $balance,
        private \DateTimeImmutable $spentAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CreditsSpent';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->spentAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'amount' => $this->amount,
            'reason' => $this->reason,
            'balance' => $this->balance,
            'spentAt' => $this->spentAt->format(\DATE_ATOM),
        ];
    }
}
