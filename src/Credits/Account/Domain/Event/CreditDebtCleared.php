<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Event;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * El saldo ha vuelto a cero o más (`FEAT-CRD-018` `RN-7`).
 *
 * **El cruce, no el estado.** Se publica al subir y no otra vez mientras la
 * cuenta siga a flote, igual que su opuesto.
 *
 * Es lo que desbloquea todo de golpe: recibir correcciones otra vez, y las
 * que llegaron bloqueadas mientras había deuda (`RN-10`). No hay desbloqueo
 * parcial, y no lo hay a propósito: es más simple y el resultado agregado es
 * el mismo.
 *
 * `Credits` no manda desbloquear nada. Dice que la deuda se saldó; qué
 * significa eso lo decide cada contexto en su modelo.
 */
final readonly class CreditDebtCleared implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private int $balance,
        private \DateTimeImmutable $clearedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CreditDebtCleared';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->clearedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'balance' => $this->balance,
            'clearedAt' => $this->clearedAt->format(\DATE_ATOM),
        ];
    }
}
