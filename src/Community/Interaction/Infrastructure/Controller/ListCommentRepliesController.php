<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Handler\ListCommentRepliesHandler;
use LectoresBeta\Community\Interaction\Application\Query\ListCommentReplies;
use LectoresBeta\Community\Interaction\Infrastructure\Http\CommentsBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/comments/{commentId}/replies` (`FEAT-COM-031`).
 */
#[AsController]
final readonly class ListCommentRepliesController
{
    public function __construct(
        private ListCommentRepliesHandler $replies,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $commentId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(CommentsBody::of(($this->replies)(new ListCommentReplies(
            $commentId,
            $user->getUserIdentifier(),
            CommentsBody::cursor($request),
            CommentsBody::limit($request),
        ))));
    }
}
