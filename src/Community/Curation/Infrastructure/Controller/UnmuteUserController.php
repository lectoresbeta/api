<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Infrastructure\Controller;

use LectoresBeta\Community\Curation\Application\Command\UnmuteUser;
use LectoresBeta\Community\Curation\Application\Handler\UnmuteUserHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/users/{userId}/muted` (`FEAT-COM-033` `RN-4`).
 */
#[AsController]
final readonly class UnmuteUserController
{
    public function __construct(
        private UnmuteUserHandler $unmute,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->unmute)(new UnmuteUser($user->getUserIdentifier(), $userId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
