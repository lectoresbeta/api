<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Command\RepostPost;
use LectoresBeta\Community\Interaction\Application\Handler\RepostPostHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/posts/{postId}/repost` (`FEAT-COM-019`).
 *
 * **El botón alterna**: volver a pulsarlo deshace el repost. Responde `200`
 * con el estado en que queda, y no `201`, porque la misma llamada puede dejar
 * las dos cosas y un código que dice «creado» estaría mintiendo la mitad de
 * las veces.
 */
#[AsController]
final readonly class RepostPostController
{
    public function __construct(
        private RepostPostHandler $repost,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $reposted = ($this->repost)(new RepostPost(
            $postId,
            $user->getUserIdentifier(),
            JsonBody::of($request)->string('comment'),
        ));

        return new JsonResponse(['reposted' => $reposted]);
    }
}
