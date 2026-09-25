<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Command\CommentOnChapter;
use LectoresBeta\Community\Interaction\Application\Handler\CommentOnChapterHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/chapters/{chapterId}/comments` (`FEAT-COM-036`).
 *
 * **Comentar un capítulo no es corregirlo.** Esto no mueve un crédito; la
 * corrección es `POST /api/v1/chapters/{chapterId}/corrections`, es el
 * cuestionario del autor, hay una por lector y capítulo, y sí los mueve.
 * Conviven en la misma pantalla y son dos cosas.
 */
#[AsController]
final readonly class CommentOnChapterController
{
    public function __construct(
        private CommentOnChapterHandler $comment,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $chapterId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        return new JsonResponse([
            'commentId' => ($this->comment)(new CommentOnChapter(
                $user->getUserIdentifier(),
                $chapterId,
                $body->string('body'),
                $body->string('parentCommentId'),
            )),
        ], Response::HTTP_CREATED);
    }
}
