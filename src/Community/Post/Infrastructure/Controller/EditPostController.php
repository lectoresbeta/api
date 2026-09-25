<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Controller;

use LectoresBeta\Community\Post\Application\Command\EditPost;
use LectoresBeta\Community\Post\Application\Handler\EditPostHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PATCH /api/v1/posts/{postId}` (`FEAT-COM-002` `RN-13`).
 *
 * Solo lleva el texto. La audiencia y el adjunto se fijan al publicar, así
 * que no hay nada más que enviar.
 */
#[AsController]
final readonly class EditPostController
{
    public function __construct(
        private EditPostHandler $edit,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->edit)(new EditPost(
            $postId,
            $user->getUserIdentifier(),
            JsonBody::of($request)->string('body'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
