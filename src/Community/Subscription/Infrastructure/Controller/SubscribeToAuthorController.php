<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Infrastructure\Controller;

use LectoresBeta\Community\Subscription\Application\Command\SubscribeToAuthor;
use LectoresBeta\Community\Subscription\Application\Handler\SubscribeToAuthorHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/users/{userId}/subscription` (`FEAT-COM-010`).
 *
 * `PUT` y no `POST` porque **el resultado es el estado, no un suceso**:
 * seguir a quien ya sigues deja el mundo igual, que es la definición de
 * idempotente. Con `POST` acabaría devolviendo `201` la primera vez y `409`
 * después, y ese `409` sería decirle a alguien que ha fallado cuando lo que
 * pedía ya se cumple.
 */
#[AsController]
final readonly class SubscribeToAuthorController
{
    public function __construct(
        private SubscribeToAuthorHandler $subscribe,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        $viewer = $this->security->getUser();

        if (null === $viewer) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->subscribe)(new SubscribeToAuthor($viewer->getUserIdentifier(), $userId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
