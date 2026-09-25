<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\CreditBalanceWentNegative;
use LectoresBeta\Notification\Delivery\Application\Event\CreditDebtCleared;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * Los dos cruces del descubierto (`FEAT-CRD-018` `RN-7`, `RN-12`).
 *
 * El aviso de bajada **tiene que explicar la salida, no solo el problema**.
 * Quien descubre que debe créditos y no sabe qué hacer se va, y la salida es
 * concreta y sana: corregir a otros salda la deuda sola, sin ningún gesto de
 * «pagar». El `payload` lleva el saldo para que la frase pueda decir cuánto.
 *
 * El de subida existe porque el desbloqueo es automático y **por tanto
 * invisible**: sin avisar, quien repuso no se entera de que ya puede volver a
 * recibir correcciones ni de que las que llegaron bloqueadas están abiertas.
 */
final readonly class NotifyTheAuthorAboutTheirDebt
{
    public function __construct(private Notify $notify)
    {
    }

    public function wentNegative(CreditBalanceWentNegative $event): void
    {
        $this->notify->deliver(
            $event->userId,
            NotificationKind::BALANCE_WENT_NEGATIVE,
            $event->eventId(),
            ['balance' => $event->balance],
        );
    }

    public function debtCleared(CreditDebtCleared $event): void
    {
        $this->notify->deliver(
            $event->userId,
            NotificationKind::CORRECTION_UNLOCKED,
            $event->eventId(),
            ['balance' => $event->balance],
        );
    }
}
