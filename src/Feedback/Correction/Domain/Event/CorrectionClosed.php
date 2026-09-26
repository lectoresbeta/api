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
 * Alguien estaba corrigiendo algo que ha dejado de estar disponible
 * ([`FEAT-MOD-003`](../../../../../docs/features/moderation/FEAT-MOD-003-block-work.md)
 * `RN-4`, [`FEAT-WRK-006`](../../../../../docs/features/work/FEAT-WRK-006-delete-work.md)
 * `RN-4`, [`FEAT-WRK-008`](../../../../../docs/features/work/FEAT-WRK-008-work-and-chapter-visibility.md)
 * `RN-5`).
 *
 * Tres causas y un solo hecho, porque para quien estaba escribiendo son lo
 * mismo: **su trabajo ya no se puede entregar**. Lo descubría al intentarlo,
 * que es tarde y desconcertante.
 *
 * **El borrador no se borra.** Es texto suyo, y el día que el contenido
 * vuelva —un bloqueo levantado, una obra recuperada, un capítulo que se
 * vuelve a mostrar— sigue ahí.
 *
 * `reason` importa para redactar el aviso: conviene que el mensaje no le haga
 * pensar que ha hecho algo mal.
 */
final readonly class CorrectionClosed implements IntegrationEvent
{
    public const WORK_BLOCKED = 'WORK_BLOCKED';
    public const WORK_ARCHIVED = 'WORK_ARCHIVED';
    public const CHAPTER_HIDDEN = 'CHAPTER_HIDDEN';

    public function __construct(
        private EventId $eventId,
        private CorrectionId $correctionId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private ReaderId $readerId,
        private string $reason,
        private \DateTimeImmutable $closedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CorrectionClosed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function payload(): array
    {
        return [
            'correctionId' => $this->correctionId->value(),
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'readerId' => $this->readerId->value(),
            'reason' => $this->reason,
            'closedAt' => $this->closedAt->format(\DATE_ATOM),
        ];
    }
}
