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
 * El autor ha marcado una corrección como útil (`FEAT-FBK-006`).
 *
 * **Solo la primera vez**, y solo la positiva. Cambiar la valoración después
 * no publica nada: avisar a alguien de que su corrección ha dejado de ser
 * útil es una crueldad sin función, y no hay nada que contar con ello.
 *
 * **`Credits` no lo consume.** La bonificación automática que hubo aquí se
 * sustituyó por la propina, que el autor decide y paga
 * ([`decision:0006`](../../../../../docs/decisions/0006-credit-system.md)). Un
 * abono automático convertiría el pulgar en un botón de dinero.
 */
final readonly class FeedbackRatedPositively implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private CorrectionId $correctionId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private ReaderId $readerId,
        private \DateTimeImmutable $ratedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'FeedbackRatedPositively';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->ratedAt;
    }

    public function payload(): array
    {
        return [
            'correctionId' => $this->correctionId->value(),
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'readerId' => $this->readerId->value(),
            'ratedAt' => $this->ratedAt->format(\DATE_ATOM),
        ];
    }
}
