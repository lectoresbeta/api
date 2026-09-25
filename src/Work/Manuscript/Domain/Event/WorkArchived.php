<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * El autor ha retirado su obra (`FEAT-WRK-006`).
 *
 * **Archivada, no borrada.** Lo que cuelga de ella —correcciones pagadas,
 * reclamaciones, movimientos que la citan— sigue existiendo: lo que cambia es
 * que deja de verla todo el mundo menos su autor.
 *
 * Quien lo escuche tiene trabajo que hacer: `Reading` retira los accesos,
 * `Feedback` cierra lo que se estuviera corrigiendo, `Credits` deja de
 * ofrecer sus capítulos, `Community` la saca de muros y recomendaciones.
 */
final readonly class WorkArchived implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private WorkId $workId,
        private AuthorId $authorId,
        private \DateTimeImmutable $archivedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WorkArchived';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'archivedAt' => $this->archivedAt->format(\DATE_ATOM),
        ];
    }
}
