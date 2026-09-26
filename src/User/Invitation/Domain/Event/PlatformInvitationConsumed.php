<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien se ha registrado con la invitación de otro (`FEAT-USR-018`).
 *
 * **No paga nada.** Dice quién invitó a quién, y ya. La recompensa llega
 * cuando el invitado entrega su primera corrección (`FEAT-CRD-005`), que es
 * todo el diseño antifraude: falsear esto cuesta una corrección de verdad.
 *
 * Lo escucha `Credits`, que se queda con el par y espera. Que sea un hecho y
 * no una llamada es lo que mantiene a `Credits` sin depender de nadie.
 */
final readonly class PlatformInvitationConsumed implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $invitationId,
        private string $inviterId,
        private string $inviteeId,
        private \DateTimeImmutable $consumedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'PlatformInvitationConsumed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function payload(): array
    {
        return [
            'invitationId' => $this->invitationId,
            'inviterId' => $this->inviterId,
            'inviteeId' => $this->inviteeId,
            'consumedAt' => $this->consumedAt->format(\DATE_ATOM),
        ];
    }
}
