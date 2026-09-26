<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Manuscript\Application\Command\SetAccessMode;
use LectoresBeta\Work\Manuscript\Application\Handler\SetAccessModeHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/works/{workId}/access-mode` (`FEAT-WRK-007`).
 */
#[AsController]
final readonly class SetAccessModeController
{
    public function __construct(
        private SetAccessModeHandler $setMode,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $mode = ($this->setMode)(new SetAccessMode(
            $workId,
            $user->getUserIdentifier(),
            (string) JsonBody::of($request)->string('accessMode'),
        ));

        return new JsonResponse(['accessMode' => $mode]);
    }
}
