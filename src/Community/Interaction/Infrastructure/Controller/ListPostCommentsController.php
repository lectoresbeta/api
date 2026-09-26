<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Handler\ListPostCommentsHandler;
use LectoresBeta\Community\Interaction\Application\Query\ListPostComments;
use LectoresBeta\Community\Interaction\Infrastructure\Http\CommentsBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/posts/{postId}/comments` (`FEAT-COM-006`).
 *
 * Solo los de **primer nivel**: las respuestas cuelgan de su comentario y se
 * piden aparte, porque mezclarlas obligaría al cliente a reconstruir el árbol
 * desde una lista plana.
 */
#[AsController]
final readonly class ListPostCommentsController
{
    public function __construct(
        private ListPostCommentsHandler $comments,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(CommentsBody::of(($this->comments)(new ListPostComments(
            $postId,
            $user->getUserIdentifier(),
            CommentsBody::sort($request),
            CommentsBody::cursor($request),
            CommentsBody::limit($request),
        ))));
    }
}
