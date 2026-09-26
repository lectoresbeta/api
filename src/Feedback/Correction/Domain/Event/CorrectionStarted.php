<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Event;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Somebody has started correcting a chapter (`FEAT-FBK-003`).
 *
 * It is published **without waiting for anybody**. Nothing is reserved, so
 * there is nothing to confirm: the reader is already writing by the time this
 * leaves. What `Credits` does with it — quote the price that will be charged
 * and earned — is its own business, and this context never learns the figure
 * ([`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)).
 */
final readonly class CorrectionStarted implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private AuthorId $authorId,
        private ReaderId $readerId,
        private \DateTimeImmutable $startedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CorrectionStarted';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'readerId' => $this->readerId->value(),
            'startedAt' => $this->startedAt->format(\DATE_ATOM),
        ];
    }
}
