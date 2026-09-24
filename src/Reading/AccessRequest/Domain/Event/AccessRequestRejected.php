<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Domain\Event;

use LectoresBeta\Reading\AccessRequest\Domain\ValueObject\AccessRequestId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * The author said no (`FEAT-RDG-003`).
 *
 * **Without a reason, because there is none to carry**: a rejection that
 * costs a written justification turns into no answer at all, and the reader
 * ends up with neither access nor a reply. A no is the information they need
 * to go and find another work.
 *
 * Cancelling, by contrast, publishes nothing: nobody was waiting for a
 * question the reader withdrew himself.
 */
final readonly class AccessRequestRejected implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private AccessRequestId $accessRequestId,
        private WorkId $workId,
        private ReaderId $readerId,
        private \DateTimeImmutable $rejectedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'AccessRequestRejected';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function payload(): array
    {
        return [
            'accessRequestId' => $this->accessRequestId->value(),
            'workId' => $this->workId->value(),
            'readerId' => $this->readerId->value(),
            'rejectedAt' => $this->rejectedAt->format(\DATE_ATOM),
        ];
    }
}
