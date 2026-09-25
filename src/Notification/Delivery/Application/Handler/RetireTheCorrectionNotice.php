<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\CorrectionRead;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * El autor ha abierto la corrección, así que el aviso sobra (`FEAT-FBK-004`
 * `RN-7`).
 *
 * Es idempotente sin necesitar registro de eventos procesados: marcar como
 * leído algo que ya lo está no cambia nada, porque la sentencia solo toca las
 * filas con `read_at IS NULL`.
 *
 * El destinatario es **el autor**, que es quien recibió el aviso de que había
 * una corrección y quien acaba de abrirla.
 */
final readonly class RetireTheCorrectionNotice
{
    public function __construct(
        private NotificationRepository $notifications,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(CorrectionRead $event): void
    {
        try {
            $recipient = RecipientId::fromString($event->authorId);
        } catch (InvalidValue) {
            return;
        }

        $this->session->execute(function () use ($recipient, $event): void {
            $this->notifications->markReadByCorrection($recipient, $event->correctionId, $this->clock->now());
        });
    }
}
