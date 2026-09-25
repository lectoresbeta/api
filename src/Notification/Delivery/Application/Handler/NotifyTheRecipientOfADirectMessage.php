<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\DirectMessageSent;
use LectoresBeta\Notification\Delivery\Application\Service\Notify;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * «Tienes un mensaje de X» (`FEAT-COM-011` `RN-8`, `RN-9`).
 *
 * El aviso lleva **quién** y **dónde**, y ni una línea de lo que dice. Se lee
 * entrando en la conversación, que es donde se comprueba que quien lee es
 * parte de ella.
 *
 * Solo por la campana: `DIRECT_MESSAGE_RECEIVED` no sale por correo
 * (`FEAT-NOT-002` `RN-1`). Un mensaje directo es ritmo de conversación —
 * llegan seguidos, a veces varios por minuto— y un correo por cada uno enseña
 * a ignorar el remitente.
 *
 * Un aviso **por mensaje**, no uno por conversación: el identificador del
 * hecho es distinto cada vez, así que dos mensajes seguidos dejan dos filas.
 * Agruparlos es trabajo de la pantalla, que sabe cuáles están abiertos.
 */
final readonly class NotifyTheRecipientOfADirectMessage
{
    public function __construct(private Notify $notify)
    {
    }

    public function __invoke(DirectMessageSent $event): void
    {
        $this->notify->deliver(
            $event->recipientId,
            NotificationKind::DIRECT_MESSAGE_RECEIVED,
            $event->eventId(),
            [
                ...$this->notify->actor($event->senderId),
                'conversationId' => $event->conversationId,
            ],
            $event->senderId,
        );
    }
}
