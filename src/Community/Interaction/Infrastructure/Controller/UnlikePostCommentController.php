<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Command\LikePostComment;
use LectoresBeta\Community\Interaction\Application\Handler\LikePostCommentHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/post-comments/{commentId}/like` (`FEAT-COM-030`).
 *
 * `post-comments` y no `comments` a secas porque `Feedback` también tiene
 * comentarios, y una ruta llamada `/comments/{id}` no diría cuál de los dos.
 */
#[AsController]
final readonly class UnlikePostCommentController
{
    public function __construct(
        private LikePostCommentHandler $likes,
        private Security $security,
    ) {
    }

    public function __invoke(string $commentId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse([
            'liked' => false,
            'likeCount' => $this->likes->unlike(new LikePostComment($user->getUserIdentifier(), $commentId)),
        ]);
    }
}
