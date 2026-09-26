<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\CorrectionClosed;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Lo que estabas corrigiendo ha dejado de estar disponible».
 *
 * Que alguien pierda un trabajo a medias porque el autor bloquea, retira u
 * oculta lo que estaba corrigiendo es inevitable. Que se entere al intentar
 * entregarlo, no.
 *
 * El aviso lleva `reason` para que el texto pueda distinguir las tres causas,
 * y **su borrador se conserva**: es texto suyo, y si el contenido vuelve,
 * sigue ahí.
 */
final readonly class NotifyTheReaderOfAClosedCorrection
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(CorrectionClosed $event): void
    {
        $this->notify->deliver(
            $event->readerId,
            NotificationKind::CORRECTION_CLOSED,
            $event->eventId(),
            [
                ...$this->notify->work($event->workId),
                'correctionId' => $event->correctionId,
                'chapterId' => $event->chapterId,
                'reason' => $event->reason,
            ],
        );
    }
}
