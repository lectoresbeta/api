<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Domain\Event;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Un administrador ha ordenado un ajuste manual de créditos
 * (`FEAT-MOD-005` `RN-3`).
 *
 * **Lleva el importe, y eso merece una explicación** porque la regla general
 * es que un hecho no le diga a `Credits` cuánto mover. La excepción está
 * escrita en la propia regla: el importe viaja cuando es **genuinamente parte
 * del hecho de origen**, y aquí lo es — lo que ha ocurrido es que una persona
 * con autoridad ha decidido mover exactamente esa cantidad, y decidirla era
 * el acto.
 *
 * Lo que `Credits` sigue decidiendo es todo lo demás: que sea un movimiento
 * nuevo y no una edición (`RN-3`), y que se contabilice como **grifo** y no
 * como transferencia (`RN-4`). Esa segunda es la que más fácil se pasa por
 * alto: un ajuste manual crea o destruye créditos de la nada, y si no se
 * registra como tal, la invariante contable empezará a fallar y nadie sabrá
 * por qué ([`FEAT-CRD-012`](../../../../../docs/features/credits/FEAT-CRD-012-economy-health.md)).
 *
 * El motivo viaja porque sin él el movimiento es inexplicable seis meses
 * después, que es justo cuando alguien pregunta.
 */
final readonly class CreditAdjustmentOrdered implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private PartyId $userId,
        private PartyId $orderedBy,
        private int $amount,
        private string $reason,
        private ?string $claimId,
        private \DateTimeImmutable $orderedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CreditAdjustmentOrdered';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->orderedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'orderedBy' => $this->orderedBy->value(),
            'amount' => $this->amount,
            'reason' => $this->reason,
            'claimId' => $this->claimId,
            'orderedAt' => $this->orderedAt->format(\DATE_ATOM),
        ];
    }
}
