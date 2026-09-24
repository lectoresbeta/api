<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Event;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * El lector ha descartado su borrador (`FEAT-FBK-011` `RN-7`).
 *
 * Guardar un borrador no es un hecho de negocio; **descartarlo sí**, y esa
 * asimetría no es caprichosa: empezar ocupó dos cosas que ahora se sueltan.
 *
 * - la **cotización del precio**, y con ella uno de los tres sitios de
 *   corrección del capítulo (`FEAT-CRD-009`);
 * - el **acceso de lector beta**, si nació de esa corrección
 *   (`FEAT-RDG-001`).
 *
 * Nada de eso lo decide este contexto. Publica que alguien se echó atrás, y
 * cada consumidor sabe qué deshacer.
 *
 * `workId` viaja porque el acceso se concede por obra, no por capítulo.
 */
final readonly class CorrectionDraftDiscarded implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private ReaderId $readerId,
        private \DateTimeImmutable $discardedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CorrectionDraftDiscarded';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->discardedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'readerId' => $this->readerId->value(),
            'discardedAt' => $this->discardedAt->format(\DATE_ATOM),
        ];
    }
}
