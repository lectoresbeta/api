<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Controller;

use LectoresBeta\Community\Post\Application\Command\DeletePost;
use LectoresBeta\Community\Post\Application\Handler\DeletePostHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/posts/{postId}` (`FEAT-COM-002` `RN-13`).
 */
#[AsController]
final readonly class DeletePostController
{
    public function __construct(
        private DeletePostHandler $delete,
        private Security $security,
    ) {
    }

    public function __invoke(string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->delete)(new DeletePost($postId, $user->getUserIdentifier()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
