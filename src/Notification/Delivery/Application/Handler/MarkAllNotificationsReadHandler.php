<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Command\MarkAllNotificationsRead;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Vaciar el contador (`FEAT-NOT-009` `RN-5`).
 *
 * En una sentencia y no fila a fila: quien lleva meses sin entrar puede tener
 * cientos de avisos, y cargarlos todos para marcarlos sería pagar la bandeja
 * entera por vaciar un número.
 */
final readonly class MarkAllNotificationsReadHandler
{
    public function __construct(
        private NotificationRepository $notifications,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(MarkAllNotificationsRead $command): void
    {
        try {
            $recipient = RecipientId::fromString($command->userId);
        } catch (InvalidValue) {
            return;
        }

        $this->session->execute(function () use ($recipient): void {
            $this->notifications->markAllRead($recipient, $this->clock->now());
        });
    }
}
