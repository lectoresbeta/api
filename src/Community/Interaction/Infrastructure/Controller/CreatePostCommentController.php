<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Command\CreatePostComment;
use LectoresBeta\Community\Interaction\Application\Handler\CreatePostCommentHandler;
use LectoresBeta\Community\Mention\Infrastructure\Http\MentionsInBody;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/posts/{postId}/comments` (`FEAT-COM-006`).
 */
#[AsController]
final readonly class CreatePostCommentController
{
    public function __construct(
        private CreatePostCommentHandler $comment,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        $commentId = ($this->comment)(new CreatePostComment(
            $postId,
            $user->getUserIdentifier(),
            $body->string('body'),
            null,
            MentionsInBody::of($body),
        ));

        return new JsonResponse(['commentId' => $commentId], Response::HTTP_CREATED);
    }
}
