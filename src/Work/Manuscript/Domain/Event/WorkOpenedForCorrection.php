<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * The author has opened the door to corrections (`FEAT-WRK-016`).
 *
 * **It moves no credits.** The earlier design reserved them at this point;
 * [`decision:0006`](../../../../../docs/decisions/0006-credit-system.md)
 * removed reservations entirely, so what this fact does is simpler than it
 * used to be: it tells `Credits` to work out which chapters of this work are
 * affordable now, and nothing is set aside.
 */
final readonly class WorkOpenedForCorrection implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private WorkId $workId,
        private AuthorId $authorId,
        private int $version,
        private \DateTimeImmutable $openedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WorkOpenedForCorrection';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            // Cuál de las decisiones sobre la puerta es esta. Quien la
            // escucha aplica solo las más nuevas que la última aplicada: una
            // cola reentrega y no promete orden, y con la fecha no basta
            // porque dos transiciones del mismo segundo son indistinguibles.
            'version' => $this->version,
            'openedAt' => $this->openedAt->format(\DATE_ATOM),
        ];
    }
}
