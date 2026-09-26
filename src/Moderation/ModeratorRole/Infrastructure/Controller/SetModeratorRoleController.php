<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Infrastructure\Controller;

use LectoresBeta\Moderation\ModeratorRole\Application\Command\SetModeratorRole;
use LectoresBeta\Moderation\ModeratorRole\Application\Handler\SetModeratorRoleHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/admin/users/{userId}/moderator-role` (`FEAT-MOD-004` `RN-1`).
 *
 * `PUT` porque fija un estado: qué es esa cuenta en moderación. `level` a
 * `null` la deja en nada, que es una respuesta como las otras dos.
 *
 * **Solo un `Admin`**, y quien lo comprueba es el firewall: la ruta exige
 * `ROLE_ADMIN`, resuelto contra la base de datos en cada petición porque el
 * token no lleva roles.
 */
#[AsController]
final readonly class SetModeratorRoleController
{
    public function __construct(
        private SetModeratorRoleHandler $setRole,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $userId): Response
    {
        $admin = $this->security->getUser();

        if (null === $admin) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->setRole)(new SetModeratorRole(
            $admin->getUserIdentifier(),
            $userId,
            JsonBody::of($request)->string('level'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
