<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\UserMentioned;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Te han mencionado» (`FEAT-COM-032`).
 *
 * `MENTION` estaba en el catálogo desde `FEAT-NOT-001`, con casilla en
 * Configuración y sin nada que lo disparara: la casilla prometía un control
 * sobre un aviso que no existía.
 *
 * **Aquí no se comprueba si el mencionado puede ver dónde se le menciona**
 * (`RN-4`), y no es un olvido: esa comprobación la hizo `Community` antes de
 * publicar el hecho, que es quien conoce la audiencia. Repetirla desde aquí
 * sería preguntarle por síncrono al contexto que el evento existe para no
 * molestar — y, peor, sería un segundo sitio donde la regla puede quedarse
 * corta.
 *
 * Que nadie se mencione a sí mismo también viene decidido de origen
 * (`RN-5`), y `Notify` lo vuelve a garantizar de todas formas.
 */
final readonly class NotifyTheMentionedPerson
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(UserMentioned $event): void
    {
        $this->notify->deliver(
            $event->mentionedUserId,
            NotificationKind::MENTION,
            $event->eventId(),
            [
                ...$this->notify->actor($event->byUserId),
                'postId' => $event->postId,
                // Dónde estaba la mención, que es lo que el cliente necesita
                // para llevar a la publicación o al comentario concreto.
                'subjectKind' => $event->subjectKind,
                'subjectId' => $event->subjectId,
            ],
            actorId: $event->byUserId,
        );
    }
}
