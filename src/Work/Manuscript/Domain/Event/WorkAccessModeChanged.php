<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * The author has changed who may become a beta reader (`FEAT-WRK-007`).
 *
 * `Reading` needs it to decide who may ask for access **from now on**. It
 * does not revoke anything: somebody already inside stays inside, because
 * cutting them off mid-correction would destroy work they have already done
 * (`RN-3`).
 */
final readonly class WorkAccessModeChanged implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private WorkId $workId,
        private AuthorId $authorId,
        private string $accessMode,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WorkAccessModeChanged';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'accessMode' => $this->accessMode,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
