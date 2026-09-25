<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\ChapterCommented;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Han comentado tu capítulo» (`FEAT-COM-036`).
 *
 * `CHAPTER_COMMENT` llevaba en el catálogo desde `FEAT-NOT-001` con su frase
 * escrita y sin nadie que lo disparara. Esto lo enciende.
 *
 * **Dos destinatarios cuando es una respuesta**, y hay que separarlos: el
 * autor de la obra se entera de que hay conversación bajo su texto, y quien
 * escribió el comentario se entera de que le han contestado. Son dos avisos
 * porque son dos hechos distintos para dos personas distintas.
 *
 * Nadie se avisa a sí mismo: el autor que comenta su propio capítulo no
 * recibe nada, y quien se responde a sí mismo tampoco.
 */
final readonly class NotifyAboutAChapterComment
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(ChapterCommented $event): void
    {
        $payload = [
            ...$this->notify->actor($event->commentAuthorId),
            ...$this->notify->work($event->workId),
            'chapterId' => $event->chapterId,
            'commentId' => $event->commentId,
        ];

        $this->notify->deliver(
            $event->workAuthorId,
            NotificationKind::CHAPTER_COMMENT,
            $event->eventId(),
            $payload,
            actorId: $event->commentAuthorId,
        );

        if (null === $event->parentAuthorId || $event->parentAuthorId === $event->workAuthorId) {
            return;
        }

        // El mismo hecho, otro destinatario y **otro tipo**: por eso el
        // índice único `(destinatario, tipo, evento)` no los confunde. Si
        // fueran el mismo tipo, el segundo aviso sería el mismo aviso.
        $this->notify->deliver(
            $event->parentAuthorId,
            NotificationKind::POST_REPLY,
            $event->eventId(),
            $payload,
            actorId: $event->commentAuthorId,
        );
    }
}
