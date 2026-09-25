<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien ha decidido si quiere que le tiendan el gancho de reactivación
 * (`FEAT-CRD-019` `RN-2d`, `RN-8`).
 *
 * Es un hecho aparte de `NotificationPreferencesChanged` y no un campo suyo,
 * y la diferencia es la que hay entre traza y contrato: aquel dice qué tocó
 * alguien en una pantalla, este dice **la respuesta**. Un contexto que
 * necesita saber si puede seleccionar a esta persona no debería tener que
 * reconstruirla interpretando una lista de tipos de aviso y un interruptor
 * general.
 *
 * Que `Credits` lo reciba por la cola, y no preguntando, es lo que mantiene
 * al contexto de créditos aislado ([`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)):
 * `Credits` no conoce a `User`, y `User` no sabe qué hace `Credits` con esto.
 *
 * `accepted` es la respuesta **efectiva**, con el interruptor general ya
 * aplicado: quien silencia todo ha renunciado, aunque la casilla del tipo
 * siga marcada.
 */
final readonly class ReactivationOfferChoiceChanged implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $userId,
        private bool $accepted,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'ReactivationOfferChoiceChanged';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'accepted' => $this->accepted,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
