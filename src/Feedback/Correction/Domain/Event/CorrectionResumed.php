<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Event;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien vuelve a abrir el panel de una corrección que ya tenía empezada
 * (`FEAT-FBK-003`).
 *
 * **No es empezar otra vez**, y por eso no es `CorrectionStarted`: no toma un
 * hueco ni fija un precio, porque las dos cosas ocurrieron la primera vez.
 * Publicarlo como un comienzo haría que `Credits` cotizara dos veces un solo
 * trabajo, que es justo lo que evita devolver la misma corrección.
 *
 * Existe porque `Reading` necesita saberlo (`R-22`). En una obra `PUBLIC`,
 * revocar un acceso no deja a nadie fuera: quien siga corrigiendo vuelve a
 * entrar ([`FEAT-RDG-010`](../../../../../docs/features/reading/FEAT-RDG-010-revoke-beta-reader-access.md)
 * `RN-7`). Quien conservaba un borrador no tenía por dónde: reanudar no
 * publicaba nada, y su acceso solo volvía si la cola repetía un mensaje
 * antiguo — es decir, por accidente. Ahora vuelve por la puerta.
 *
 * Que llegue aquí significa que esa persona **puede corregir ahora mismo**:
 * `EligibleCorrectionBrief` se ejecuta antes y rechaza a quien perdió el
 * acceso a una obra cerrada. Por eso quien lo consume no tiene que mirar la
 * modalidad de la obra, igual que con un comienzo.
 */
final readonly class CorrectionResumed implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private CorrectionId $correctionId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private AuthorId $authorId,
        private ReaderId $readerId,
        private \DateTimeImmutable $resumedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CorrectionResumed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->resumedAt;
    }

    public function payload(): array
    {
        return [
            'correctionId' => $this->correctionId->value(),
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'readerId' => $this->readerId->value(),
            'resumedAt' => $this->resumedAt->format(\DATE_ATOM),
        ];
    }
}
