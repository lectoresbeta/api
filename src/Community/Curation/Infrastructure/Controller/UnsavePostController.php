<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Infrastructure\Controller;

use LectoresBeta\Community\Curation\Application\Command\UnsavePost;
use LectoresBeta\Community\Curation\Application\Handler\UnsavePostHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/posts/{postId}/saved` (`FEAT-COM-021` `RN-4`).
 *
 * Quitar de guardados lo que no estaba guardado responde igual: el desenlace
 * es el mismo.
 */
#[AsController]
final readonly class UnsavePostController
{
    public function __construct(
        private UnsavePostHandler $handler,
        private Security $security,
    ) {
    }

    public function __invoke(string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->handler)(new UnsavePost($user->getUserIdentifier(), $postId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
