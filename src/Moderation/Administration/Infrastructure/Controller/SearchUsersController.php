<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Infrastructure\Controller;

use LectoresBeta\Moderation\Administration\Application\Handler\SearchUsersHandler;
use LectoresBeta\Moderation\Administration\Application\Query\SearchUsers;
use LectoresBeta\Moderation\Administration\Infrastructure\Http\AdminAccountPayload;
use LectoresBeta\User\Account\Application\Contract\AdministrableAccount;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/admin/users` (`FEAT-MOD-005`).
 *
 * Busca por correo, nombre de usuario o nombre. El correo es el término que
 * de verdad se usa: quien atiende a una persona tiene su dirección porque se
 * la ha escrito ella.
 *
 * **La consulta queda auditada** (`RN-1`). Un backoffice donde mirar es
 * invisible es un backoffice donde se puede curiosear.
 */
#[AsController]
final readonly class SearchUsersController
{
    public function __construct(
        private SearchUsersHandler $search,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $moderator = $this->security->getUser();

        if (null === $moderator) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $found = ($this->search)(new SearchUsers(
            $moderator->getUserIdentifier(),
            $request->query->get('q'),
            $request->query->getInt('limit', 25),
            $request->query->getInt('offset'),
        ));

        $response = new JsonResponse([
            'users' => array_map(
                static fn (AdministrableAccount $account): array => AdminAccountPayload::of($account),
                $found,
            ),
        ]);

        // Lleva direcciones de correo: no se guarda en ningún intermediario.
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
