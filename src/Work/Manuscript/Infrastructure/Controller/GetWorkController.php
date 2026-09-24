<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Work\Manuscript\Application\DTO\ChapterSummary;
use LectoresBeta\Work\Manuscript\Application\Handler\GetWorkHandler;
use LectoresBeta\Work\Manuscript\Application\Query\GetWork;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/works/{workId}` (`FEAT-WRK-004`).
 *
 * Metadatos e **índice** de capítulos, nunca su texto. El índice de una
 * novela es información inocua y el texto no lo es: tienen audiencias
 * distintas, y mezclarlos obligaría a la regla más estricta a gobernar las
 * dos.
 */
#[AsController]
final readonly class GetWorkController
{
    public function __construct(
        private GetWorkHandler $work,
        private Security $security,
    ) {
    }

    public function __invoke(string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $work = ($this->work)(new GetWork($workId, $user->getUserIdentifier()));

        return new JsonResponse([
            'workId' => $work->workId,
            'authorId' => $work->authorId,
            'title' => $work->title,
            'synopsis' => $work->synopsis,
            'status' => $work->status,
            'accessMode' => $work->accessMode,
            'adultsOnly' => $work->adultsOnly,
            'wordCount' => $work->wordCount,
            'blocked' => $work->blocked,
            'genres' => $work->genres,
            'chapters' => array_map(
                static fn (ChapterSummary $chapter): array => [
                    'chapterId' => $chapter->chapterId,
                    'position' => $chapter->position,
                    'title' => $chapter->title,
                    'wordCount' => $chapter->wordCount,
                ],
                $work->chapters,
            ),
        ]);
    }
}
