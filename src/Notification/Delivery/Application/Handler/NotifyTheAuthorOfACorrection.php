<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\FeedbackSubmitted;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Han corregido tu obra» (`FEAT-NOT-001`).
 *
 * Lo que el autor estaba esperando desde que la abrió a corrección.
 *
 * **Sin una línea del texto** (`RN-4`): el aviso dice que hay algo que leer,
 * y se lee dentro. Es la misma regla que impide que una obra inédita salga de
 * la plataforma por correo, aplicada al canal que sí se queda dentro —porque
 * la regla es del aviso, no del canal.
 */
final readonly class NotifyTheAuthorOfACorrection
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(FeedbackSubmitted $event): void
    {
        $this->notify->deliver(
            $event->authorId,
            NotificationKind::CORRECTION_RECEIVED,
            $event->eventId(),
            [
                ...$this->notify->actor($event->readerId),
                ...$this->notify->work($event->workId),
                'correctionId' => $event->correctionId,
                'chapterId' => $event->chapterId,
            ],
            actorId: $event->readerId,
        );
    }
}
