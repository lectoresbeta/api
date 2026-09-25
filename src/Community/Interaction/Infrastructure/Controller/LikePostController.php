<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Command\LikePost;
use LectoresBeta\Community\Interaction\Application\Handler\LikePostHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/posts/{postId}/like` (`FEAT-COM-008`).
 *
 * `PUT` y no `POST` porque **fija un estado**: la publicación acaba apoyada
 * por quien llama, haya pulsado una vez o cinco. Es la forma honesta de decir
 * que repetirlo no suma.
 *
 * Devuelve el contador, que es lo que la pantalla necesita para repintarse
 * sin una segunda petición.
 */
#[AsController]
final readonly class LikePostController
{
    public function __construct(
        private LikePostHandler $likes,
        private Security $security,
    ) {
    }

    public function __invoke(string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse([
            'liked' => true,
            'likeCount' => $this->likes->like(new LikePost($user->getUserIdentifier(), $postId)),
        ]);
    }
}
