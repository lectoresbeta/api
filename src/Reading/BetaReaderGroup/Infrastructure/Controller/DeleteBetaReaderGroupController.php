<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Controller;

use LectoresBeta\Reading\BetaReaderGroup\Application\Command\DeleteBetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Application\Handler\DeleteBetaReaderGroupHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/beta-reader-groups/{groupId}` (`FEAT-RDG-007` `RN-10`).
 *
 * Se lleva a los miembros y no toca ninguna invitación ya cursada.
 */
#[AsController]
final readonly class DeleteBetaReaderGroupController
{
    public function __construct(
        private DeleteBetaReaderGroupHandler $delete,
        private Security $security,
    ) {
    }

    public function __invoke(string $groupId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->delete)(new DeleteBetaReaderGroup($groupId, $user->getUserIdentifier()));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
