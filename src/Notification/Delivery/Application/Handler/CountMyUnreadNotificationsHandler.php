<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Query\CountMyUnreadNotifications;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * Cuántos avisos sin leer (`FEAT-NOT-009` `RN-3`).
 *
 * **Cuenta todos, sin tope y sin «99+»**. El recorte es de presentación, y un
 * backend que devuelva `99` cuando hay 1.480 obliga a la pantalla a mentir
 * dos veces: al enseñar el número y cuando alguien abre la bandeja y cuenta.
 */
final readonly class CountMyUnreadNotificationsHandler
{
    public function __construct(private NotificationRepository $notifications)
    {
    }

    public function __invoke(CountMyUnreadNotifications $query): int
    {
        try {
            $recipient = RecipientId::fromString($query->userId);
        } catch (InvalidValue) {
            return 0;
        }

        return $this->notifications->countUnread($recipient);
    }
}
