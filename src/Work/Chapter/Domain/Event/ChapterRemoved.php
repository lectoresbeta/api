<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Un capítulo ha dejado de existir (`FEAT-WRK-003`).
 *
 * Solo ocurre con capítulos que **nadie ha corregido**: los demás se ocultan,
 * porque borrarlos destruiría el trabajo de quien los corrigió y el rastro de
 * un cobro.
 *
 * Lleva el orden que queda porque quien mantiene precios necesita las dos
 * cosas a la vez —qué desaparece y dónde queda el final de la obra— y
 * pedirlas por separado sería dos hechos para un cambio.
 */
final readonly class ChapterRemoved implements IntegrationEvent
{
    /**
     * @param list<string> $order
     */
    public function __construct(
        private EventId $eventId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private AuthorId $authorId,
        private array $order,
        private \DateTimeImmutable $removedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'ChapterRemoved';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->removedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'order' => $this->order,
            'removedAt' => $this->removedAt->format(\DATE_ATOM),
        ];
    }
}
