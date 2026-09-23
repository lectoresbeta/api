<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * A work is out of the drawer (`FEAT-WRK-016`).
 *
 * The first fact of this context that other people can act on: `Reading`,
 * `Community` and `Notification` all wait for it. **It does not mean the work
 * accepts corrections** — that is a second, deliberate decision by the author,
 * and its own event.
 */
final readonly class WorkPublished implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private WorkId $workId,
        private AuthorId $authorId,
        private string $title,
        private int $wordCount,
        private int $chapterCount,
        private \DateTimeImmutable $publishedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WorkPublished';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'title' => $this->title,
            'wordCount' => $this->wordCount,
            'chapterCount' => $this->chapterCount,
            'publishedAt' => $this->publishedAt->format(\DATE_ATOM),
        ];
    }
}
