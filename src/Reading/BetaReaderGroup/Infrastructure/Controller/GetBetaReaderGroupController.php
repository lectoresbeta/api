<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Controller;

use LectoresBeta\Reading\BetaReaderGroup\Application\Handler\GetBetaReaderGroupHandler;
use LectoresBeta\Reading\BetaReaderGroup\Application\Query\GetBetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Http\BetaReaderGroupBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/beta-reader-groups/{groupId}` (`FEAT-RDG-007`).
 *
 * El grupo de otra persona responde `404`, igual que uno inexistente.
 */
#[AsController]
final readonly class GetBetaReaderGroupController
{
    public function __construct(
        private GetBetaReaderGroupHandler $get,
        private Security $security,
    ) {
    }

    public function __invoke(string $groupId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $group = ($this->get)(new GetBetaReaderGroup($groupId, $user->getUserIdentifier()));

        return new JsonResponse(BetaReaderGroupBody::detail($group));
    }
}
