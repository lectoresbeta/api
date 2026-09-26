<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Infrastructure\Controller;

use LectoresBeta\Community\Relationship\Application\Command\UnblockUser;
use LectoresBeta\Community\Relationship\Application\Handler\UnblockUserHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/users/{userId}/block` (`FEAT-COM-034`).
 *
 * Siempre `204`. Desbloquear a quien no estaba bloqueado no es un fallo, y
 * **no devuelve los seguimientos** que el bloqueo deshizo: hay que volver a
 * seguir.
 */
#[AsController]
final readonly class UnblockUserController
{
    public function __construct(
        private UnblockUserHandler $unblock,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        $viewer = $this->security->getUser();

        if (null === $viewer) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->unblock)(new UnblockUser($viewer->getUserIdentifier(), $userId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
