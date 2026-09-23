<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Infrastructure\Controller;

use LectoresBeta\Work\Chapter\Application\Handler\GetChapterHandler;
use LectoresBeta\Work\Chapter\Application\Query\GetChapter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/chapters/{chapterId}` (`FEAT-WRK-004`).
 *
 * Devuelve el HTML **exactamente como está almacenado**. Se saneó al
 * escribirlo, que es justamente el sentido de sanear en la escritura: no se
 * guarda nada que no se estuviera dispuesto a servir, así que servir no
 * necesita una segunda pasada.
 */
#[AsController]
final readonly class GetChapterController
{
    public function __construct(
        private GetChapterHandler $chapter,
        private Security $security,
    ) {
    }

    public function __invoke(string $chapterId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $chapter = ($this->chapter)(new GetChapter($chapterId, $user->getUserIdentifier()));

        return new JsonResponse([
            'chapterId' => $chapter->chapterId,
            'workId' => $chapter->workId,
            'position' => $chapter->position,
            'title' => $chapter->title,
            'content' => $chapter->contentHtml,
            'wordCount' => $chapter->wordCount,
        ]);
    }
}
