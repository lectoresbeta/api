<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Handler\ListChapterCommentsHandler;
use LectoresBeta\Community\Interaction\Application\Query\ListChapterComments;
use LectoresBeta\Community\Interaction\Infrastructure\Http\ChapterCommentsBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/chapters/{chapterId}/comments` (`FEAT-COM-036`).
 *
 * Del más reciente al más antiguo. **No hay «Más relevantes»** todavía: esa
 * ordenación necesita la fórmula de relevancia que `CM-4` deja sin definir, y
 * que tiene bloqueados también los rankings y el orden del muro.
 *
 * Solo los de primer nivel. Las respuestas cuelgan de su comentario y se
 * piden aparte, igual que en el muro: mezclarlas obligaría al cliente a
 * reconstruir el árbol desde una lista plana.
 */
#[AsController]
final readonly class ListChapterCommentsController
{
    public function __construct(
        private ListChapterCommentsHandler $comments,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $chapterId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $page = ($this->comments)(new ListChapterComments(
            $user->getUserIdentifier(),
            $chapterId,
            ChapterCommentsBody::cursor($request),
            ChapterCommentsBody::limit($request),
        ));

        return new JsonResponse(ChapterCommentsBody::of($page['comments'], $page['nextCursor']));
    }
}
