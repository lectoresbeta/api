<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * The door is closed to **new** corrections (`FEAT-WRK-016` `RN-7`).
 *
 * What it is not: an undo. Feedback already received stays, and anybody who
 * had started correcting finishes and gets paid — a reader who has spent
 * hours on a chapter cannot lose that because the author changed their mind.
 */
final readonly class WorkClosedForCorrection implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private WorkId $workId,
        private AuthorId $authorId,
        private \DateTimeImmutable $closedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WorkClosedForCorrection';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'closedAt' => $this->closedAt->format(\DATE_ATOM),
        ];
    }
}
