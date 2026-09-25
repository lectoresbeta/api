<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Event;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * A balance has just crossed below zero (`FEAT-CRD-006` `RN-3`).
 *
 * **Crossed**, not «is negative»: it is published on the movement that takes
 * an account under, and not again while it stays there. A consumer that got
 * one per charge could not tell the moment it happened from the state it is
 * in, and the moment is what deserves a notice and what locks the correction
 * that has just arrived ([`FEAT-CRD-018`](../../../../../docs/features/credits/FEAT-CRD-018-negative-balance.md)).
 *
 * That an author can go negative at all is the price of holding nothing back,
 * and it is what guarantees the other half: **a reader is paid for work they
 * have already done**, whatever the author's balance says.
 */
/**
 * El saldo ha cruzado a negativo (`FEAT-CRD-018`).
 *
 * **El cruce, no el estado**: se publica al bajar y no otra vez mientras la
 * cuenta siga debajo.
 *
 * `subjectId` dice de qué era el movimiento que lo provocó —hoy, una
 * corrección—. Va aquí porque es parte del hecho y no una orden: el saldo
 * bajó *por eso*. Quien lo consume decide qué significa, y a `Feedback` le
 * sirve para bloquear esa corrección sin tocar las que el autor ya había
 * leído.
 */
final readonly class CreditBalanceWentNegative implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private int $balance,
        private \DateTimeImmutable $crossedAt,
        private ?string $subjectId = null,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CreditBalanceWentNegative';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->crossedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'balance' => $this->balance,
            'crossedAt' => $this->crossedAt->format(\DATE_ATOM),
            // Qué movimiento cruzó. Es parte del hecho —el saldo bajó *por
            // esto*— y no una instrucción: quien lo consume decide qué
            // significa. `Feedback` lo usa para bloquear esa corrección y no
            // las que el autor ya había leído (`FEAT-CRD-018` `RN-11`).
            'subjectId' => $this->subjectId,
        ];
    }
}
