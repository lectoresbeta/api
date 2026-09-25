<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Event;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien sin cuenta ha corregido por un enlace público (`FEAT-FBK-008`).
 *
 * **`Credits` no lo consume, y esa ausencia es la especificación**: es la
 * forma más clara de decir que este camino está fuera de la economía. No hay
 * cargo al autor y no hay abono a nadie, porque al otro lado no hay cuenta.
 *
 * Sin `readerId`, que es justamente lo que lo distingue de
 * `FeedbackSubmitted`. Lleva `authorLabel` —el nombre que esa persona
 * escribió para sí misma— porque al autor le importa distinguir la crítica de
 * su hermana de la de un compañero de taller. Es una etiqueta, no una
 * identidad, y nadie la ha verificado.
 *
 * Ni una palabra de las respuestas: son texto privado entre dos personas y no
 * tienen nada que hacer en una cola que persiste, reintenta y aparca
 * mensajes.
 */
final readonly class PublicCorrectionSubmitted implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private CorrectionId $correctionId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private AuthorId $authorId,
        private ?string $authorLabel,
        private \DateTimeImmutable $submittedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'PublicCorrectionSubmitted';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function payload(): array
    {
        return [
            'correctionId' => $this->correctionId->value(),
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'authorLabel' => $this->authorLabel,
            'submittedAt' => $this->submittedAt->format(\DATE_ATOM),
        ];
    }
}
