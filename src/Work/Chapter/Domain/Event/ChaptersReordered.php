<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * El autor ha cambiado el orden de los capítulos (`FEAT-WRK-003`).
 *
 * Parece cosmético y no lo es: **el último capítulo es el que responde las
 * preguntas de alcance `LAST_CHAPTER`**, así que reordenar cambia lo que se
 * pide —y lo que se paga— en dos capítulos, el que deja de ser el último y el
 * que pasa a serlo.
 *
 * Lleva la lista entera en su orden nuevo, no «este se movió allí»: así
 * aplicarlo dos veces da el mismo resultado, que es lo único que sobrevive a
 * una cola que entrega al menos una vez.
 */
final readonly class ChaptersReordered implements IntegrationEvent
{
    /**
     * @param list<string> $order
     */
    public function __construct(
        private EventId $eventId,
        private WorkId $workId,
        private AuthorId $authorId,
        private array $order,
        private \DateTimeImmutable $reorderedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'ChaptersReordered';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->reorderedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'order' => $this->order,
            'reorderedAt' => $this->reorderedAt->format(\DATE_ATOM),
        ];
    }
}
