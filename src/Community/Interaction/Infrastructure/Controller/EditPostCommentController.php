<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Command\EditPostComment;
use LectoresBeta\Community\Interaction\Application\Handler\EditPostCommentHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PATCH /api/v1/comments/{commentId}` (`FEAT-COM-006`, `I-3`).
 */
#[AsController]
final readonly class EditPostCommentController
{
    public function __construct(
        private EditPostCommentHandler $edit,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $commentId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->edit)(new EditPostComment(
            $commentId,
            $user->getUserIdentifier(),
            JsonBody::of($request)->string('body'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
