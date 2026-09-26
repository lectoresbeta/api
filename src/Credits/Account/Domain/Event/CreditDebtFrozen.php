<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Event;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * La deuda de alguien deja de retener, y hasta cuándo (`FEAT-CRD-018`
 * `RN-8b`, `FEAT-MOD-006` `RN-9`).
 *
 * **No es `CreditDebtCleared`, y reutilizarlo habría sido mentir dos veces:**
 * su carga lleva el saldo, que aquí sigue en rojo, y su nombre dice que la
 * deuda se saldó, que es lo contrario de lo que ha pasado. Lo que ha pasado
 * es que quien la tiene está cumpliendo una suspensión parcial y no puede
 * corregir, que es la única forma de saldarla.
 *
 * **Lleva la fecha de fin, no un «está congelada».** Quien lo recibe guarda
 * la fecha y deja de preguntar: la congelación se apaga sola cuando el plazo
 * vence, sin un segundo hecho que anunciarlo y sin nadie a quien culpar si
 * ese segundo hecho no llega.
 *
 * No lleva saldo. Cuánto se debe es asunto de `Credits`; lo que cruza la
 * frontera es que durante este plazo la deuda no retiene.
 */
final readonly class CreditDebtFrozen implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private \DateTimeImmutable $frozenUntil,
        private \DateTimeImmutable $frozenAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CreditDebtFrozen';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->frozenAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'frozenUntil' => $this->frozenUntil->format(\DATE_ATOM),
            'frozenAt' => $this->frozenAt->format(\DATE_ATOM),
        ];
    }
}
