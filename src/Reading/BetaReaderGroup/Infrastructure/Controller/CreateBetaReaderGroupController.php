<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Controller;

use LectoresBeta\Reading\BetaReaderGroup\Application\Command\CreateBetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Application\Handler\CreateBetaReaderGroupHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/me/beta-reader-groups` (`FEAT-RDG-007`).
 *
 * Cuelga de `/me/` porque no existe la versión de otra persona: un grupo es
 * una anotación privada del autor.
 */
#[AsController]
final readonly class CreateBetaReaderGroupController
{
    public function __construct(
        private CreateBetaReaderGroupHandler $create,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $group = ($this->create)(new CreateBetaReaderGroup(
            $user->getUserIdentifier(),
            JsonBody::of($request)->string('name'),
        ));

        return new JsonResponse([
            'groupId' => $group->id()->value(),
            'name' => $group->name()->value(),
            'memberCount' => 0,
            'createdAt' => $group->createdAt()->format(\DATE_ATOM),
        ], Response::HTTP_CREATED);
    }
}
