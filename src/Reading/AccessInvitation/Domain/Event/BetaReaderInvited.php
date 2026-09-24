<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Domain\Event;

use LectoresBeta\Reading\AccessInvitation\Domain\ValueObject\AccessInvitationId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * The author has offered their work to somebody (`FEAT-RDG-004`).
 *
 * **Without the author's message**, for the same reason `AccessRequested`
 * goes without the reader's: it is prose, it can be long, and the notice only
 * has to say there is something waiting.
 *
 * It grants nothing. Whoever receives it decides in `FEAT-RDG-005`.
 */
final readonly class BetaReaderInvited implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private AccessInvitationId $invitationId,
        private WorkId $workId,
        private AuthorId $authorId,
        private ReaderId $readerId,
        private \DateTimeImmutable $invitedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'BetaReaderInvited';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function payload(): array
    {
        return [
            'invitationId' => $this->invitationId->value(),
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'readerId' => $this->readerId->value(),
            'invitedAt' => $this->invitedAt->format(\DATE_ATOM),
        ];
    }
}
