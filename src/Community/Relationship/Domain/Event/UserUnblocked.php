<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Domain\Event;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Se ha levantado un bloqueo (`FEAT-COM-034` `RN-10`).
 *
 * Deshace lo que cada consumidor aplicó, **y solo eso**: desbloquear no
 * restaura los seguimientos que el bloqueo deshizo (`RN-7`) ni devuelve un
 * acceso de lector beta revocado. Volver atrás del todo sería reconstruir un
 * pasado que ya no existe; lo que se recupera es la posibilidad de empezar
 * otra vez.
 */
final readonly class UserUnblocked implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private MemberId $blockerId,
        private MemberId $blockedId,
        private \DateTimeImmutable $unblockedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'UserUnblocked';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->unblockedAt;
    }

    public function payload(): array
    {
        return [
            'blockerId' => $this->blockerId->value(),
            'blockedId' => $this->blockedId->value(),
            'unblockedAt' => $this->unblockedAt->format(\DATE_ATOM),
        ];
    }
}
