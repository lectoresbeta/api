<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Event;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * A beta reader has delivered a correction (`FEAT-FBK-003` `RN-7`).
 *
 * **The most important fact in the system**: it is what moves credits, and
 * the whole product exists so that it happens.
 *
 * It describes the fact and not its consequences. No amount — `Feedback`
 * neither knows nor should know what a correction is worth — and **not a
 * word of the answers**, which are private material between two people and
 * have no business on a queue that persists, retries and parks messages.
 *
 * `questionnaireVersion` travels because the author needs to know which set
 * of questions this replies to: they may have rewritten it while the reader
 * was writing.
 */
final readonly class FeedbackSubmitted implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private CorrectionId $correctionId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private AuthorId $authorId,
        private ReaderId $readerId,
        private int $questionnaireVersion,
        private \DateTimeImmutable $submittedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'FeedbackSubmitted';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function payload(): array
    {
        return [
            'correctionId' => $this->correctionId->value(),
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'readerId' => $this->readerId->value(),
            'questionnaireVersion' => $this->questionnaireVersion,
            'submittedAt' => $this->submittedAt->format(\DATE_ATOM),
        ];
    }
}
