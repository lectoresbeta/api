<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Composition;

use LectoresBeta\Notification\Delivery\Application\Contract\UnreadNotificationCount;
use LectoresBeta\User\Profile\Application\Port\SessionSideloads;
use Psr\Log\LoggerInterface;

/**
 * Lo que el contexto de sesión pide fuera, con `RN-7` aplicado aquí
 * (`FEAT-USR-027`).
 *
 * **Degrada en lugar de fallar.** El punto de la campana viene de un contexto
 * que puede caerse por su cuenta, y que no se pueda pintar no debería impedir
 * navegar. Vive en Infrastructure porque de fallos y tiempos de espera sabe
 * Infrastructure, no el caso de uso.
 *
 * El detalle del fallo va al registro y nunca a la respuesta: fuera solo
 * viaja un `null`, que el cliente ya sabe leer como «ahora mismo no se sabe».
 */
final readonly class NotificationSideloads implements SessionSideloads
{
    public function __construct(
        private UnreadNotificationCount $unread,
        private LoggerInterface $logger,
    ) {
    }

    public function unreadNotifications(string $userId): ?int
    {
        try {
            return $this->unread->forRecipient($userId);
        } catch (\Throwable $failure) {
            $this->logger->error('The unread notification count could not be read.', ['exception' => $failure]);

            return null;
        }
    }
}
