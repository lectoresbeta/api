<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * El autor ha recuperado una obra que había retirado (`FEAT-WRK-006`
 * `RN-7`).
 *
 * Vuelve **a borrador**, nunca publicada: reabrir la puerta es una decisión
 * aparte, y tomarla por él sería volver a enseñar una obra que retiró.
 */
final readonly class WorkRestored implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private WorkId $workId,
        private AuthorId $authorId,
        private \DateTimeImmutable $restoredAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WorkRestored';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->restoredAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'restoredAt' => $this->restoredAt->format(\DATE_ATOM),
        ];
    }
}
