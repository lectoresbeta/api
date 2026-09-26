<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Infrastructure\Controller;

use LectoresBeta\Community\Relationship\Application\Command\BlockUser;
use LectoresBeta\Community\Relationship\Application\Handler\BlockUserHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/users/{userId}/block` (`FEAT-COM-034`).
 *
 * Como seguir: `PUT` sobre el estado de la relación, e idempotente. Bloquear
 * a quien ya está bloqueado deja el mundo igual.
 */
#[AsController]
final readonly class BlockUserController
{
    public function __construct(
        private BlockUserHandler $block,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        $viewer = $this->security->getUser();

        if (null === $viewer) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->block)(new BlockUser($viewer->getUserIdentifier(), $userId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
