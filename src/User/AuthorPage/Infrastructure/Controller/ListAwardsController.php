<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\User\AuthorPage\Application\Handler\ListAwardsHandler;
use LectoresBeta\User\AuthorPage\Application\Query\ListAwards;
use LectoresBeta\User\AuthorPage\Infrastructure\Http\AwardBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/users/{userId}/awards` (`FEAT-USR-030`).
 *
 * **Público**, como el perfil del que forma parte. La sesión se lee si la hay
 * y sirve para una sola cosa: que su titular se vea siempre a sí mismo,
 * aunque haya restringido su perfil.
 */
#[AsController]
final readonly class ListAwardsController
{
    public function __construct(
        private ListAwardsHandler $awards,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        return new JsonResponse(AwardBody::listOf(($this->awards)(new ListAwards(
            $userId,
            $this->security->getUser()?->getUserIdentifier(),
        ))));
    }
}
