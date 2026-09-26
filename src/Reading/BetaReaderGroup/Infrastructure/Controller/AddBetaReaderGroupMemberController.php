<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Controller;

use LectoresBeta\Reading\BetaReaderGroup\Application\Command\AddBetaReaderGroupMember;
use LectoresBeta\Reading\BetaReaderGroup\Application\Handler\AddBetaReaderGroupMemberHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/beta-reader-groups/{groupId}/members/{readerId}`
 * (`FEAT-RDG-007` `RN-8`).
 *
 * Es `PUT` y no `POST` porque lo que el autor quiere decir es «esta persona
 * está en el grupo», no «añade una fila». Repetirlo no cambia nada.
 *
 * **No le concede acceso a ninguna obra** y esa persona no se entera.
 */
#[AsController]
final readonly class AddBetaReaderGroupMemberController
{
    public function __construct(
        private AddBetaReaderGroupMemberHandler $add,
        private Security $security,
    ) {
    }

    public function __invoke(string $groupId, string $readerId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->add)(new AddBetaReaderGroupMember($groupId, $user->getUserIdentifier(), $readerId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
