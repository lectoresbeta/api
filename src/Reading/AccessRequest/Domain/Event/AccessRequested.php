<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Domain\Event;

use LectoresBeta\Reading\AccessRequest\Domain\ValueObject\AccessRequestId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Somebody wants to read a work (`FEAT-RDG-002`).
 *
 * **Without the reader's message.** The text is theirs, it can be long, and
 * the notice does not need it: it says there is something to resolve, and
 * whoever resolves it opens it. It is the same reason `QuestionnaireUpdated`
 * does not carry the author's questions — a queue that persists, retries and
 * parks messages is no place for somebody's prose.
 *
 * It grants nothing. A request is a question with an acknowledgement.
 */
final readonly class AccessRequested implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private AccessRequestId $accessRequestId,
        private WorkId $workId,
        private AuthorId $authorId,
        private ReaderId $readerId,
        private \DateTimeImmutable $requestedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'AccessRequested';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function payload(): array
    {
        return [
            'accessRequestId' => $this->accessRequestId->value(),
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'readerId' => $this->readerId->value(),
            'requestedAt' => $this->requestedAt->format(\DATE_ATOM),
        ];
    }
}
