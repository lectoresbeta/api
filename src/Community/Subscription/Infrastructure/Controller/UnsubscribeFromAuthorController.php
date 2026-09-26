<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Infrastructure\Controller;

use LectoresBeta\Community\Subscription\Application\Command\UnsubscribeFromAuthor;
use LectoresBeta\Community\Subscription\Application\Handler\UnsubscribeFromAuthorHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/users/{userId}/subscription` (`FEAT-COM-010`).
 *
 * Siempre `204`, se siguiera o no. Dejar de seguir a quien no se sigue no es
 * un fallo: el estado que se pedía ya se cumple.
 */
#[AsController]
final readonly class UnsubscribeFromAuthorController
{
    public function __construct(
        private UnsubscribeFromAuthorHandler $unsubscribe,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        $viewer = $this->security->getUser();

        if (null === $viewer) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->unsubscribe)(new UnsubscribeFromAuthor($viewer->getUserIdentifier(), $userId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
