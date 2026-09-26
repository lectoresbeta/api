<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Rating\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Un lector beta ha valorado una obra (`FEAT-FBK-002`).
 *
 * **Lleva la nota, y es de los pocos hechos del sistema que lleva una cifra.**
 * Se justifica igual que `ChapterPriceChanged`: el valor *es* el hecho, y
 * quien lo consuma —los rankings— necesitaría preguntarlo de vuelta si no
 * viajara aquí.
 *
 * Se publica también al **cambiar** una valoración anterior. Quien promedia
 * necesita enterarse de las dos cosas, y un hecho que solo cubre la primera
 * vez dejaría la media congelada en la primera impresión de cada lector.
 *
 * Hoy **no lo consume nadie**. Los tres rankings siguen bloqueados por `CM-4`
 * —la fórmula de puntuación no está definida— y publicar el hecho igualmente
 * es lo que permite que el día que se defina haya histórico que promediar.
 */
final readonly class WorkRated implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $workId,
        private string $authorId,
        private string $readerId,
        private int $rating,
        private bool $firstTime,
        private \DateTimeImmutable $ratedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WorkRated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->ratedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'readerId' => $this->readerId,
            'rating' => $this->rating,
            // Para que quien promedie sepa si suma una nota o sustituye una
            // que ya tenía contada.
            'firstTime' => $this->firstTime,
            'ratedAt' => $this->ratedAt->format(\DATE_ATOM),
        ];
    }
}
