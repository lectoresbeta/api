<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\WritingBuddyProposed;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Te proponen ser writing buddy» (`FEAT-RDG-008`).
 *
 * Es el aviso que faltaba para cerrar `FEAT-NOT-005`: solicitudes,
 * invitaciones **y propuestas**. Los dos primeros ya avisaban; este llevaba
 * su frase escrita desde `FEAT-NOT-001` y nadie lo disparaba.
 *
 * **Solo se avisa de la propuesta, no del rechazo.** Quien propuso lo ve en
 * su lista; avisarle de un «no» convertiría una respuesta discreta en un
 * desaire con acuse de recibo.
 */
final readonly class NotifyTheProposedWritingBuddy
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(WritingBuddyProposed $event): void
    {
        $this->notify->deliver(
            $event->partnerId,
            NotificationKind::WRITING_BUDDY_PROPOSED,
            $event->eventId(),
            [
                ...$this->notify->actor($event->proposerId),
                'linkId' => $event->linkId,
            ],
            actorId: $event->proposerId,
        );
    }
}
