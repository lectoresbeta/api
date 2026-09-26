<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\User\AuthorPage\Application\Command\DeleteAward;
use LectoresBeta\User\AuthorPage\Application\Handler\DeleteAwardHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/me/awards/{awardId}` (`FEAT-USR-030`).
 */
#[AsController]
final readonly class DeleteAwardController
{
    public function __construct(
        private DeleteAwardHandler $delete,
        private Security $security,
    ) {
    }

    public function __invoke(string $awardId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->delete)(new DeleteAward($user->getUserIdentifier(), $awardId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
