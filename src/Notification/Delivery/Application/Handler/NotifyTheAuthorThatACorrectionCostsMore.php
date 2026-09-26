<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\ChapterPriceChanged;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * «Ampliar este capítulo lo ha encarecido» (`FEAT-CRD-016` `RN-9`, `C-15`).
 *
 * **Solo cuando sube.** Un capítulo que se abarata no interrumpe a nadie, y
 * uno que estrena precio tampoco: no había antes con el que compararlo. La
 * pregunta la responde el propio hecho, que desde ahora lleva el precio
 * anterior — sin él, quien avisa tendría que recordar la última cifra que
 * vio, y eso es guardar estado de precios en el contexto que no los calcula.
 *
 * Solo campana, nunca correo: es la consecuencia inmediata de algo que el
 * autor acaba de hacer, y se entera mientras sigue en la pantalla donde lo
 * hizo. Un correo por cada guardado sería el que enseña a ignorar el
 * remitente.
 *
 * De quién es la obra lo pregunta este consumidor por el contrato publicado
 * de `Work`. El hecho no lo lleva, y está bien que no lo lleve: es un hecho
 * sobre un precio, no sobre una persona.
 */
final readonly class NotifyTheAuthorThatACorrectionCostsMore
{
    public function __construct(
        private Notify $notify,
        private WorkAccessBriefs $works,
    ) {
    }

    public function __invoke(ChapterPriceChanged $event): void
    {
        if (!$event->isMoreExpensive()) {
            return;
        }

        $work = $this->works->ofWork($event->workId);

        if (null === $work) {
            return;
        }

        $this->notify->deliver(
            $work->authorId,
            NotificationKind::CHAPTER_PRICE_INCREASED,
            $event->eventId(),
            [
                ...$this->notify->work($event->workId),
                'chapterId' => $event->chapterId,
                'credits' => $event->credits,
                'previousCredits' => $event->previousCredits,
            ],
        );
    }
}
