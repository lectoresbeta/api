<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Infrastructure\Controller;

use LectoresBeta\Notification\Delivery\Application\Handler\ListMyNotificationsHandler;
use LectoresBeta\Notification\Delivery\Application\Query\ListMyNotifications;
use LectoresBeta\Notification\Delivery\Infrastructure\Http\InboxBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/notifications` (`FEAT-NOT-009`).
 *
 * **Solo la propia, y no hay otra forma de pedirla**: el destinatario sale de
 * la sesión, así que no existe parámetro que apunte a otra persona.
 *
 * No exige la cuenta activada. La bandeja es de los primeros sitios donde
 * alguien sin activar necesita entrar, porque ahí está el aviso de que active.
 */
#[AsController]
final readonly class ListMyNotificationsController
{
    public function __construct(
        private ListMyNotificationsHandler $inbox,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(InboxBody::of(($this->inbox)(new ListMyNotifications(
            $user->getUserIdentifier(),
            InboxBody::unreadOnly($request),
            InboxBody::limit($request),
            InboxBody::cursor($request),
        ))));
    }
}
