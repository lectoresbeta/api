<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\OverdraftCorrectionGranted;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * El gancho (`FEAT-CRD-019`).
 *
 * Alguien ha corregido un texto que su autor dejó abierto y ya no podía
 * pagar. El aviso es la mitad del mecanismo: sin él, lo que queda es una
 * deuda que su dueño descubre por casualidad.
 *
 * El mensaje se escribe solo, porque es la tesis de la plataforma en
 * miniatura: **para leer una corrección, haz una corrección**. No es «paga
 * para ver» — es reponer el saldo haciendo por otro exactamente lo que
 * alguien acaba de hacer por ti.
 *
 * Viaja `creditsNeeded` y no el saldo: la meta concreta es lo que motiva, y
 * «debes −7» y «te faltan 7» no se leen igual aunque digan lo mismo.
 *
 * **Lo que no viaja es una línea del contenido.** Ni un fragmento difuminado:
 * o se regala parte del valor, o se parece a un muro de pago de periódico.
 */
final readonly class NotifyTheAuthorAboutTheOverdraftCorrection
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(OverdraftCorrectionGranted $event): void
    {
        $this->notify->deliver(
            $event->authorId,
            NotificationKind::REACTIVATION_OFFER,
            $event->eventId(),
            [
                'correctionId' => $event->correctionId,
                'chapterId' => $event->chapterId,
                'workId' => $event->workId,
                'creditsNeeded' => $event->creditsNeeded,
            ],
        );
    }
}
