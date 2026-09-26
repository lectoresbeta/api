<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;

/**
 * Somebody now has an account, still unactivated (`FEAT-USR-001`).
 *
 * It carries the email because `Notification` has to send the activation
 * message and has no business reading `User`'s tables to find it. It carries
 * neither the password nor its hash, which is the kind of thing that only
 * needs saying once but is worth a test.
 *
 * **`Credits` does not consume this.** Registering has no credit effect: the
 * welcome grant happens on activation (`FEAT-CRD-002`).
 */
final readonly class UserRegistered implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private Email $email,
        private Username $username,
        private \DateTimeImmutable $registeredAt,
        private ?UserId $invitedBy = null,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'UserRegistered';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'email' => $this->email->value(),
            'username' => $this->username->value(),
            'registeredAt' => $this->registeredAt->format(\DATE_ATOM),
            'invitedBy' => $this->invitedBy?->value(),
        ];
    }
}
