<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Domain\Event;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\WritingBuddy\Domain\ValueObject\WritingBuddyLinkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien le propone a alguien ser writing buddy (`FEAT-RDG-008`).
 *
 * **No concede nada.** Quien la recibe decide en `FEAT-RDG-009`, y ni
 * siquiera entonces se abre ninguna puerta: el vínculo no habilita accesos
 * (`R-3`).
 */
final readonly class WritingBuddyProposed implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private WritingBuddyLinkId $linkId,
        private ReaderId $proposerId,
        private ReaderId $partnerId,
        private \DateTimeImmutable $proposedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WritingBuddyProposed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->proposedAt;
    }

    public function payload(): array
    {
        return [
            'linkId' => $this->linkId->value(),
            'proposerId' => $this->proposerId->value(),
            'partnerId' => $this->partnerId->value(),
            'proposedAt' => $this->proposedAt->format(\DATE_ATOM),
        ];
    }
}
