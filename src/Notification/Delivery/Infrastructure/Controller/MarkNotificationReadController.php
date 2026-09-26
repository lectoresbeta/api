<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Infrastructure\Controller;

use LectoresBeta\Notification\Delivery\Application\Command\MarkNotificationRead;
use LectoresBeta\Notification\Delivery\Application\Handler\MarkNotificationReadHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/me/notifications/{notificationId}/read` (`FEAT-NOT-009`).
 *
 * `PUT` y no `POST` porque marcar como leído es **fijar un estado**: repetirlo
 * deja el mundo igual, y la pantalla lo repite —marca al abrir y además ofrece
 * el gesto—.
 *
 * Un aviso ajeno responde `404`, nunca `403` (`RN-6`).
 */
#[AsController]
final readonly class MarkNotificationReadController
{
    public function __construct(
        private MarkNotificationReadHandler $markRead,
        private Security $security,
    ) {
    }

    public function __invoke(string $notificationId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->markRead)(new MarkNotificationRead($user->getUserIdentifier(), $notificationId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
