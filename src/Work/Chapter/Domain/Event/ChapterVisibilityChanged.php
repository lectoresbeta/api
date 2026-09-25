<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * El autor ha ocultado o vuelto a mostrar un capítulo (`FEAT-WRK-008`).
 *
 * Ocultar **no borra nada**: las correcciones entregadas sobre ese capítulo
 * se siguen leyendo por las dos partes, y el trabajo que alguien hizo sobre
 * él sigue contando. Lo que cambia es que deja de poder leerse y de poder
 * corregirse.
 */
final readonly class ChapterVisibilityChanged implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private AuthorId $authorId,
        private string $visibility,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'ChapterVisibilityChanged';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'visibility' => $this->visibility,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
