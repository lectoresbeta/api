<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Una reclamación se ha estimado (`FEAT-MOD-002` `RN-4`).
 *
 * **Dice qué se ha estimado y sobre qué, nunca los efectos.** No lleva
 * «devuelve seis créditos» ni «bloquea la obra»: cada contexto decide qué
 * significa en su modelo, que es lo único que mantiene `Moderation` aislado.
 *
 * La tentación contraria —que el backoffice escriba en las tablas de
 * `Credits` y de `Work`— es más fácil y más rápida, y produce un contexto que
 * lo sabe todo sobre todos.
 *
 * **Tampoco lleva la motivación del moderador.** Es material interno, puede
 * ser duro, y va dirigido al expediente y no a las partes.
 */
final readonly class ClaimUpheld implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $claimId,
        private string $type,
        private string $targetType,
        private string $targetId,
        private ?string $subjectId,
        private \DateTimeImmutable $upheldAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'ClaimUpheld';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->upheldAt;
    }

    public function payload(): array
    {
        return [
            'claimId' => $this->claimId,
            'type' => $this->type,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'subjectId' => $this->subjectId,
            'upheldAt' => $this->upheldAt->format(\DATE_ATOM),
        ];
    }
}
