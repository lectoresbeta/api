<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Infrastructure\Controller;

use LectoresBeta\Notification\Delivery\Application\Handler\CountMyUnreadNotificationsHandler;
use LectoresBeta\Notification\Delivery\Application\Query\CountMyUnreadNotifications;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/notifications/unread-count` (`FEAT-NOT-009` `RN-3`).
 *
 * Existe aparte de la bandeja porque la campana se pinta en **todas** las
 * pantallas: cargar veinte filas para enseñar un `2` sería pagar la lista
 * entera en cada navegación.
 */
#[AsController]
final readonly class GetUnreadNotificationCountController
{
    public function __construct(
        private CountMyUnreadNotificationsHandler $unread,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse([
            'unreadCount' => ($this->unread)(new CountMyUnreadNotifications($user->getUserIdentifier())),
        ]);
    }
}
