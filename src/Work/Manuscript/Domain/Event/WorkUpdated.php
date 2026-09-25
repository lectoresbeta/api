<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * El autor ha cambiado el título o la sinopsis de su obra (`FEAT-WRK-005`).
 *
 * **Sin el texto de ninguno de los dos.** Quien tenga una copia de la ficha
 * —el muro, una recomendación— sabe así que tiene que volver a pedirla, y el
 * contenido de la obra sigue sin salir de este contexto, que es la regla que
 * no se rompe por comodidad.
 */
final readonly class WorkUpdated implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private WorkId $workId,
        private AuthorId $authorId,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WorkUpdated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'updatedAt' => $this->updatedAt->format(\DATE_ATOM),
        ];
    }
}
