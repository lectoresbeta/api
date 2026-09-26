<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Command\MarkNotificationRead;
use LectoresBeta\Notification\Delivery\Domain\Exception\NotificationNotFound;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Marcar un aviso como leído (`FEAT-NOT-009`).
 *
 * **Un aviso ajeno responde lo mismo que uno inexistente** (`RN-6`). Nunca
 * `403`: distinguirlos permitiría, probando identificadores, ir descubriendo
 * lo que le ocurre a otra persona.
 *
 * Marcar lo ya leído no falla ni mueve la fecha (`RN-4`). La pantalla puede
 * marcar al abrir y además ofrecer el gesto explícito (`N-7`), así que la
 * segunda llamada llega sola y no puede ser un error.
 */
final readonly class MarkNotificationReadHandler
{
    public function __construct(
        private NotificationRepository $notifications,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(MarkNotificationRead $command): void
    {
        try {
            $recipient = RecipientId::fromString($command->userId);
            $notification = $this->notifications->ofId(NotificationId::fromString($command->notificationId));
        } catch (InvalidValue) {
            throw NotificationNotFound::notification();
        }

        if (null === $notification || !$notification->isFor($recipient)) {
            throw NotificationNotFound::notification();
        }

        if ($notification->isRead()) {
            return;
        }

        $this->session->execute(function () use ($notification): void {
            $notification->markRead($this->clock->now());
            $this->notifications->save($notification);
        });
    }
}
