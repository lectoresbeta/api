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
 * `PUT /api/v1/comments/{commentId}/like` (`FEAT-COM-030`).
 *
 * `/comments/` es el prefijo que este contexto ya usa para sus comentarios
 * —editarlos, borrarlos, responderlos— y esto es una acción más sobre el
 * mismo recurso. Los de `Feedback` viven bajo `/corrections/`.
 */
#[AsController]
final readonly class LikePostCommentController
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
            'liked' => true,
            'likeCount' => $this->likes->like(new LikePostComment($user->getUserIdentifier(), $commentId)),
        ]);
    }
}
