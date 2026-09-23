<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * The account has been activated (`FEAT-USR-020`).
 *
 * The fact that pays the welcome credits — but it does not say so, and it
 * does not carry an amount. `User` publishes what happened; `Credits` decides
 * that it is worth 10 (`decision:0002`, `FEAT-CRD-002`). If the welcome grant
 * became 30 tomorrow, nothing here would change.
 */
final readonly class AccountActivated implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private \DateTimeImmutable $activatedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'AccountActivated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'activatedAt' => $this->activatedAt->format(\DATE_ATOM),
        ];
    }
}
