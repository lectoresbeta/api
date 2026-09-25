<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Controller;

use LectoresBeta\Community\Post\Application\Handler\ListPostsHandler;
use LectoresBeta\Community\Post\Application\Query\ListPosts;
use LectoresBeta\Community\Post\Infrastructure\Http\WallBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/posts` y `GET /api/v1/users/{userId}/posts`
 * (`FEAT-COM-026`).
 *
 * **Un controlador para los dos**, porque la pregunta es la misma con un
 * identificador distinto: sin `userId` en la ruta, el muro es el de quien
 * mira. Dos controladores habrían sido dos sitios donde olvidar la sesión.
 *
 * La respuesta es **idéntica en forma** a la del muro general: la misma
 * tarjeta, el mismo cursor y las mismas reglas. Mirar el perfil de alguien no
 * enseña nada que su muro no enseñara ya, y eso no es una comprobación que
 * haya que escribir aquí: sale de reutilizar la consulta.
 */
#[AsController]
final readonly class ListMemberPostsController
{
    public function __construct(
        private ListPostsHandler $wall,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, ?string $userId = null): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(WallBody::of(($this->wall)(new ListPosts(
            $user->getUserIdentifier(),
            WallBody::cursor($request),
            WallBody::limit($request),
            $userId ?? $user->getUserIdentifier(),
        ))));
    }
}
