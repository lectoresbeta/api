<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Infrastructure\Controller;

use LectoresBeta\Community\Curation\Application\Command\SavePost;
use LectoresBeta\Community\Curation\Application\Handler\SavePostHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/posts/{postId}/saved` (`FEAT-COM-021`).
 *
 * Apartar una publicación para volver a ella. **Privado**: no se cuenta, no
 * se anuncia y su autor no se entera.
 *
 * Es `PUT` y no `POST` porque lo que se dice es «esta está guardada», no
 * «añade una fila». Guardar dos veces es guardar.
 */
#[AsController]
final readonly class SavePostController
{
    public function __construct(
        private SavePostHandler $handler,
        private Security $security,
    ) {
    }

    public function __invoke(string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->handler)(new SavePost($user->getUserIdentifier(), $postId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
