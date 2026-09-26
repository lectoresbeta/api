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
 *
 * It carries the work's **genres** because they are part of what was
 * published, not a decoration: `Community` projects which genres each author
 * writes in to answer «authors you might like» without reading this context's
 * tables (`FEAT-COM-016` `RN-1`). Making it ask instead would be a query
 * across a boundary on every onboarding.
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
        /** @var list<string> */
        private array $genres,
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
            'genres' => $this->genres,
            'publishedAt' => $this->publishedAt->format(\DATE_ATOM),
        ];
    }
}
