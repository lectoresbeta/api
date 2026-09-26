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
 * `DELETE /api/v1/posts/{postId}/like` (`FEAT-COM-008`).
 *
 * Retirar lo que no se puso **responde bien**: el estado que se pedía ya se
 * cumple, y quien borra dos veces suele ser un reintento.
 *
 * Devuelve el contador, que es lo que la pantalla necesita para repintarse
 * sin una segunda petición.
 */
#[AsController]
final readonly class UnlikePostController
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
            'liked' => false,
            'likeCount' => $this->likes->unlike(new LikePost($user->getUserIdentifier(), $postId)),
        ]);
    }
}
