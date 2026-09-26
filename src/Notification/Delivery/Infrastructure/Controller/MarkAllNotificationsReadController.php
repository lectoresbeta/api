<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Infrastructure\Controller;

use LectoresBeta\Notification\Delivery\Application\Command\MarkAllNotificationsRead;
use LectoresBeta\Notification\Delivery\Application\Handler\MarkAllNotificationsReadHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/me/notifications/read` (`FEAT-NOT-009` `RN-5`).
 *
 * Siempre `204`, también sin nada que marcar: **un contador que no se puede
 * vaciar es un contador que acaba ignorado**, y fallar cuando ya está a cero
 * sería poner una pega a lo único que lo vacía.
 */
#[AsController]
final readonly class MarkAllNotificationsReadController
{
    public function __construct(
        private MarkAllNotificationsReadHandler $markAllRead,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->markAllRead)(new MarkAllNotificationsRead($user->getUserIdentifier()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
