<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\PublicCorrectionSubmitted;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Han corregido tu obra por el enlace que repartiste» (`FEAT-FBK-008`).
 *
 * El mismo tipo de aviso que el de una corrección normal, y **sin actor**:
 * al otro lado no hay cuenta, así que no hay perfil que pedirle a `User` ni a
 * quién enlazar. Lo que viaja en su lugar es la etiqueta que esa persona
 * escribió, que el cliente tiene que presentar como lo que es —un nombre sin
 * verificar— y no como un usuario de la plataforma.
 */
final readonly class NotifyTheAuthorOfAPublicCorrection
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(PublicCorrectionSubmitted $event): void
    {
        $this->notify->deliver(
            $event->authorId,
            NotificationKind::CORRECTION_RECEIVED,
            $event->eventId(),
            [
                ...$this->notify->work($event->workId),
                'correctionId' => $event->correctionId,
                'chapterId' => $event->chapterId,
                'authorLabel' => $event->authorLabel,
                'fromPublicLink' => true,
            ],
        );
    }
}
