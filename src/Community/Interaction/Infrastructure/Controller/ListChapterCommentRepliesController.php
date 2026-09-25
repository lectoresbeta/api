<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Handler\ListChapterCommentsHandler;
use LectoresBeta\Community\Interaction\Application\Query\ListChapterCommentReplies;
use LectoresBeta\Community\Interaction\Infrastructure\Http\ChapterCommentsBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/chapter-comments/{commentId}/replies` (`FEAT-COM-036`).
 *
 * De la más antigua a la más reciente: un hilo se lee en el orden en que se
 * dijo, al revés que la lista de comentarios.
 */
#[AsController]
final readonly class ListChapterCommentRepliesController
{
    public function __construct(
        private ListChapterCommentsHandler $comments,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $commentId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $page = $this->comments->replies(new ListChapterCommentReplies(
            $user->getUserIdentifier(),
            $commentId,
            ChapterCommentsBody::cursor($request),
            ChapterCommentsBody::limit($request),
        ));

        return new JsonResponse(ChapterCommentsBody::of($page['comments'], $page['nextCursor']));
    }
}
