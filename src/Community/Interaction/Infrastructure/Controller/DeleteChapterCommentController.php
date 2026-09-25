<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Command\DeleteChapterComment;
use LectoresBeta\Community\Interaction\Application\Handler\DeleteChapterCommentHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/chapter-comments/{commentId}` (`FEAT-COM-036`).
 *
 * El propio, y solo el propio. Que el autor de la obra pueda **ocultar** lo
 * que le dicen es otra cosa, con otra semántica (`FEAT-FBK-007`): ahí el
 * comentario sigue existiendo para quien lo escribió.
 */
#[AsController]
final readonly class DeleteChapterCommentController
{
    public function __construct(
        private DeleteChapterCommentHandler $delete,
        private Security $security,
    ) {
    }

    public function __invoke(string $commentId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->delete)(new DeleteChapterComment($user->getUserIdentifier(), $commentId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
