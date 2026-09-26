<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Somebody set or changed the genres they are interested in
 * (`FEAT-USR-023`).
 *
 * `Community` uses it to suggest authors. It carries the **whole** selection
 * and not what changed: a consumer that had to apply a sequence of deltas
 * would end up with a wrong set the first time a message was lost or
 * reordered, and it would have no way of noticing.
 */
final readonly class LiteraryPreferencesUpdated implements IntegrationEvent
{
    /**
     * @param list<string> $genreCodes
     */
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private array $genreCodes,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'LiteraryPreferencesUpdated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'genres' => $this->genreCodes,
        ];
    }
}
