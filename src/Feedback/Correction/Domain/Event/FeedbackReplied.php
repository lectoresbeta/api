<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Event;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * El autor ha contestado a una corrección (`FEAT-FBK-005`).
 *
 * **Sin el texto de la respuesta.** Es lo que una persona le dice a otra
 * sobre su trabajo, y una cola que persiste, reintenta y aparca mensajes no
 * es sitio para eso. El aviso dice que hay algo que leer; leerlo se hace
 * donde vive, con autorización.
 *
 * Solo la primera vez. Sustituir la respuesta no vuelve a anunciar: recibir
 * tres avisos porque alguien corrige sus erratas es ruido.
 */
final readonly class FeedbackReplied implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private CorrectionId $correctionId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private ReaderId $readerId,
        private \DateTimeImmutable $repliedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'FeedbackReplied';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->repliedAt;
    }

    public function payload(): array
    {
        return [
            'correctionId' => $this->correctionId->value(),
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'readerId' => $this->readerId->value(),
            'repliedAt' => $this->repliedAt->format(\DATE_ATOM),
        ];
    }
}
