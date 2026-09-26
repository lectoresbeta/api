<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\PostCommented;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Han comentado tu publicación» (`FEAT-COM-006` `RN-8`, `FEAT-COM-031`).
 *
 * El gemelo de `NotifyAboutAChapterComment` sobre el muro. `POST_REPLY`
 * llevaba en el catálogo desde `FEAT-NOT-001` con su frase escrita y sin
 * nadie que lo disparara; esto lo enciende.
 *
 * **Dos destinatarios cuando es una respuesta**, y son dos avisos porque son
 * dos hechos distintos: quien publicó se entera de que hay conversación bajo
 * su texto, y quien comentó se entera de que le han contestado.
 *
 * El tipo es el mismo para los dos, y el índice único no los confunde porque
 * incluye al destinatario. La única colisión posible —que quien publicó y
 * quien escribió el comentario padre sean la misma persona— se descarta
 * antes, o esa persona recibiría el mismo aviso dos veces.
 *
 * Nadie se avisa a sí mismo: lo garantiza `Notify`, y aquí se nombra el actor
 * para que lo pueda hacer.
 */
final readonly class NotifyAboutAPostComment
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(PostCommented $event): void
    {
        $payload = [
            ...$this->notify->actor($event->commentAuthorId),
            'postId' => $event->postId,
            'commentId' => $event->commentId,
        ];

        $this->notify->deliver(
            $event->postAuthorId,
            NotificationKind::POST_REPLY,
            $event->eventId(),
            $payload,
            actorId: $event->commentAuthorId,
        );

        if (null === $event->parentAuthorId || $event->parentAuthorId === $event->postAuthorId) {
            return;
        }

        $this->notify->deliver(
            $event->parentAuthorId,
            NotificationKind::POST_REPLY,
            $event->eventId(),
            $payload,
            actorId: $event->commentAuthorId,
        );
    }
}
