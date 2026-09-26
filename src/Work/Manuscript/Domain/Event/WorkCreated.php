<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * A work exists (`FEAT-WRK-001`).
 *
 * **The content never travels in it**, and neither does anything derived from
 * reading it. A work is born in `DRAFT`: it is unpublished literary work, and
 * a queue that persists, retries and parks messages is the last place it
 * belongs.
 */
final readonly class WorkCreated implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private WorkId $workId,
        private AuthorId $authorId,
        private string $title,
        private string $accessMode,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WorkCreated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'title' => $this->title,
            'accessMode' => $this->accessMode,
            'status' => 'DRAFT',
            'createdAt' => $this->createdAt->format(\DATE_ATOM),
        ];
    }
}
