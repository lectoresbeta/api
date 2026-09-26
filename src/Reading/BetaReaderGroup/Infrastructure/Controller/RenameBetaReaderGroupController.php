<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Controller;

use LectoresBeta\Reading\BetaReaderGroup\Application\Command\RenameBetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Application\Handler\RenameBetaReaderGroupHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PATCH /api/v1/beta-reader-groups/{groupId}` (`FEAT-RDG-007`).
 *
 * Lo único que se puede cambiar de un grupo es su nombre. Los miembros
 * entran y salen por su propio recurso.
 */
#[AsController]
final readonly class RenameBetaReaderGroupController
{
    public function __construct(
        private RenameBetaReaderGroupHandler $rename,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $groupId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $group = ($this->rename)(new RenameBetaReaderGroup(
            $groupId,
            $user->getUserIdentifier(),
            JsonBody::of($request)->string('name'),
        ));

        return new JsonResponse([
            'groupId' => $group->id()->value(),
            'name' => $group->name()->value(),
        ]);
    }
}
