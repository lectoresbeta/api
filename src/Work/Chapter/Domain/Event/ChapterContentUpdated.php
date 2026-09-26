<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * A chapter now holds a different text (`FEAT-WRK-001`, `FEAT-CRD-016`).
 *
 * **The fact is about a chapter and not about the work**, because the unit
 * that gets corrected is the chapter (`FEAT-CRD-016` `RN-2`) and its length
 * is the reading term of its price. A work-level total would be a number
 * nobody can act on: knowing a novel has 80.000 words says nothing about
 * what correcting its third chapter is worth.
 *
 * `position` travels with it because the last chapter is the one that
 * answers the questions of scope `LAST_CHAPTER` (`FEAT-WRK-014` `W-17`), and
 * `Credits` cannot ask `Work` which chapter that is without reaching into
 * its model.
 *
 * It carries **the count and not a word of the text**: the manuscript is
 * unpublished work and a queue that persists, retries and parks messages is
 * no place for it.
 */
final readonly class ChapterContentUpdated implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private AuthorId $authorId,
        private int $position,
        private int $wordCount,
        private \DateTimeImmutable $updatedAt,
        /** La versión del texto, que sube solo cuando se archiva la anterior. */
        private int $version = 1,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'ChapterContentUpdated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'position' => $this->position,
            'wordCount' => $this->wordCount,
            'version' => $this->version,
            'updatedAt' => $this->updatedAt->format(\DATE_ATOM),
        ];
    }
}
