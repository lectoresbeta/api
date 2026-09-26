<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Controller;

use LectoresBeta\Reading\BetaReaderGroup\Application\Command\RemoveBetaReaderGroupMember;
use LectoresBeta\Reading\BetaReaderGroup\Application\Handler\RemoveBetaReaderGroupMemberHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/beta-reader-groups/{groupId}/members/{readerId}`
 * (`FEAT-RDG-007` `RN-9`).
 *
 * **No le quita ningún acceso.** Quien ya sea lector beta de una obra lo
 * sigue siendo: retirar el acceso es `FEAT-RDG-010`.
 */
#[AsController]
final readonly class RemoveBetaReaderGroupMemberController
{
    public function __construct(
        private RemoveBetaReaderGroupMemberHandler $remove,
        private Security $security,
    ) {
    }

    public function __invoke(string $groupId, string $readerId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->remove)(new RemoveBetaReaderGroupMember($groupId, $user->getUserIdentifier(), $readerId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
