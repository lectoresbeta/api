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
 * `GET /api/v1/posts` (`FEAT-COM-001`).
 *
 * **Exige sesión, y no por costumbre**: sin saber quién mira no se puede
 * resolver qué publicaciones `FOLLOWERS` le alcanzan, y servir solo lo
 * público sería una segunda vista de la misma pantalla que habría que
 * mantener aparte.
 */
#[AsController]
final readonly class ListPostsController
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
        ))));
    }
}
