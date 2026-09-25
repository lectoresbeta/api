<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Command\DeletePostComment;
use LectoresBeta\Community\Interaction\Application\Handler\DeletePostCommentHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/comments/{commentId}` (`FEAT-COM-006`, `I-3`).
 *
 * Retirar un comentario raíz **se lleva sus respuestas**: lo contrario
 * dejaría respuestas contestando a una pregunta que nadie puede leer.
 */
#[AsController]
final readonly class DeletePostCommentController
{
    public function __construct(
        private DeletePostCommentHandler $delete,
        private Security $security,
    ) {
    }

    public function __invoke(string $commentId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->delete)(new DeletePostComment($commentId, $user->getUserIdentifier()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
