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
 * The invitee said no (`FEAT-RDG-005` `RN-6`).
 *
 * **This one is told and cancelling a request is not**, and the asymmetry is
 * the whole point: whoever cancels a request withdraws a question they asked
 * themselves, and nobody was waiting. Whoever declines an invitation answers
 * somebody who *is* waiting — an author who offered unpublished work to one
 * named person deserves to know it is a no, if only to go and ask somebody
 * else.
 *
 * Without a reason, like every other refusal here: a no that costs a
 * paragraph turns into no answer at all.
 */
final readonly class BetaReaderInvitationDeclined implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private AccessInvitationId $invitationId,
        private WorkId $workId,
        private AuthorId $authorId,
        private ReaderId $readerId,
        private \DateTimeImmutable $declinedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'BetaReaderInvitationDeclined';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->declinedAt;
    }

    public function payload(): array
    {
        return [
            'invitationId' => $this->invitationId->value(),
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'readerId' => $this->readerId->value(),
            'declinedAt' => $this->declinedAt->format(\DATE_ATOM),
        ];
    }
}
