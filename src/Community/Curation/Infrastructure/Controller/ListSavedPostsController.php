<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Infrastructure\Controller;

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
 * `GET /api/v1/me/saved-posts` (`FEAT-COM-021`).
 *
 * **Es el muro con un filtro**, la misma decisión que el muro de un perfil
 * (`FEAT-COM-026`), y de ahí sale gratis lo que importa: **guardar no
 * conserva acceso**. Una publicación que se hizo privada, cuyo autor te
 * bloqueó o que se borró deja de aparecer aquí sin que esta funcionalidad
 * tenga que saber por qué.
 *
 * La consecuencia: **el orden es el del muro**, por fecha de publicación
 * descendente, no por cuándo se guardó. Ordenar por eso exigiría un cursor
 * distinto y una consulta propia, que es justo lo que se descartó.
 */
#[AsController]
final readonly class ListSavedPostsController
{
    public function __construct(
        private ListPostsHandler $wall,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(WallBody::of(($this->wall)(new ListPosts(
            $user->getUserIdentifier(),
            WallBody::cursor($request),
            WallBody::limit($request),
            null,
            WallBody::filters($request),
            savedOnly: true,
        ))));
    }
}
