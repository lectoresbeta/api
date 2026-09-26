<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\FeedbackRatedPositively;
use LectoresBeta\Notification\Delivery\Application\Event\FeedbackReplied;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * Lo que la plataforma le devuelve a quien corrigió (`FEAT-FBK-005`,
 * `FEAT-FBK-006`).
 *
 * Dos hechos y un mismo trabajo: avisar a quien escribió una corrección de
 * que el autor **ha hecho algo con ella**. Sin esto, corregir es escribir en
 * un buzón: se entrega, se cobra, y no se sabe si sirvió de algo.
 *
 * **Solo llegan las primeras veces.** Sustituir una respuesta no vuelve a
 * anunciar, y cambiar una valoración a «no útil» no anuncia nada — las dos
 * decisiones son de quien publica los hechos, y por eso aquí no hay ninguna
 * condición que mantener.
 */
final readonly class NotifyTheReaderOfAnAnsweredCorrection
{
    public function __construct(private Notify $notify)
    {
    }

    public function replied(FeedbackReplied $event): void
    {
        $this->notify->deliver(
            $event->readerId,
            NotificationKind::CORRECTION_REPLIED,
            $event->eventId(),
            [
                ...$this->notify->work($event->workId),
                'correctionId' => $event->correctionId,
                'chapterId' => $event->chapterId,
            ],
        );
    }

    public function rated(FeedbackRatedPositively $event): void
    {
        $this->notify->deliver(
            $event->readerId,
            NotificationKind::CORRECTION_RATED,
            $event->eventId(),
            [
                ...$this->notify->work($event->workId),
                'correctionId' => $event->correctionId,
                'chapterId' => $event->chapterId,
            ],
        );
    }
}
