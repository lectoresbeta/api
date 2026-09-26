<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Command\CreatePostComment;
use LectoresBeta\Community\Interaction\Application\Handler\CreatePostCommentHandler;
use LectoresBeta\Community\Interaction\Application\Service\ReachableComment;
use LectoresBeta\Community\Mention\Infrastructure\Http\MentionsInBody;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/comments/{commentId}/replies` (`FEAT-COM-031`).
 *
 * La ruta habla de un comentario y el caso de uso de una publicación, así que
 * aquí se resuelve a cuál pertenece. Es también donde se aplica la invariante
 * del hilo plano: **si `commentId` es una respuesta, se cuelga del mismo
 * raíz**, y el cliente no necesita saber cuál es.
 *
 * La mención precargada al autor del comentario la manda el cliente como
 * cualquier otra: es una comodidad del compositor, no una obligación, y
 * quien responde puede borrarla antes de enviar.
 */
#[AsController]
final readonly class ReplyToCommentController
{
    public function __construct(
        private CreatePostCommentHandler $comment,
        private ReachableComment $reachable,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $commentId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $root = $this->reachable->rootOf($commentId, $user->getUserIdentifier());
        $body = JsonBody::of($request);

        $replyId = ($this->comment)(new CreatePostComment(
            $root->postId()->value(),
            $user->getUserIdentifier(),
            $body->string('body'),
            $root->id()->value(),
            MentionsInBody::of($body),
        ));

        return new JsonResponse(['commentId' => $replyId], Response::HTTP_CREATED);
    }
}
