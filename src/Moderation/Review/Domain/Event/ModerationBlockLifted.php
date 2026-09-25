<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Un moderador ha levantado un bloqueo (`FEAT-MOD-003` `RN-7`).
 *
 * **Quien ejecuta sigue siendo `Work`**, igual que al bloquear: el estado de
 * una obra es su modelo, y si `Moderation` lo escribiera tendría que conocer
 * sus transiciones. Aquí solo se dice qué se ha decidido.
 *
 * Sin la motivación, como todos los hechos de este contexto: es material
 * interno del expediente y va al registro de auditoría, no a una cola.
 */
final readonly class ModerationBlockLifted implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $targetType,
        private string $targetId,
        private \DateTimeImmutable $liftedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'ModerationBlockLifted';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->liftedAt;
    }

    public function payload(): array
    {
        return [
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'liftedAt' => $this->liftedAt->format(\DATE_ATOM),
        ];
    }
}
