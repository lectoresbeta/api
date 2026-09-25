<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Work\Chapter\Domain\Service\ReadingTime;
use LectoresBeta\Work\Manuscript\Application\DTO\MyWork;
use LectoresBeta\Work\Manuscript\Application\Handler\ListMyWorksHandler;
use LectoresBeta\Work\Manuscript\Application\Query\ListMyWorks;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/works` (`FEAT-WRK-015`).
 *
 * El único listado que **incluye los borradores** y el único que lleva la
 * insignia de estado. Las dos cosas son lo mismo dicho de dos maneras: esto
 * es la pantalla de gestión del autor, no un escaparate.
 *
 * Funciona con la cuenta sin activar (`RN-5`): es solo lectura.
 */
#[AsController]
final readonly class ListMyWorksController
{
    public function __construct(
        private ListMyWorksHandler $mine,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $author = $this->security->getUser();

        if (null === $author) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $page = ($this->mine)(new ListMyWorks(
            $author->getUserIdentifier(),
            $request->query->get('status'),
            $request->query->get('sort'),
            $request->query->getInt('page', 1),
            $request->query->getInt('perPage', 20),
        ));

        return new JsonResponse([
            'total' => $page->total,
            'totalPages' => $page->totalPages(),
            'page' => $page->page,
            'perPage' => $page->perPage,
            'works' => array_map(
                static fn (MyWork $work): array => [
                    'workId' => $work->workId,
                    'title' => $work->title,
                    'synopsis' => $work->synopsis,
                    'status' => $work->status,
                    'accessMode' => $work->accessMode,
                    'adultsOnly' => $work->adultsOnly,
                    'wordCount' => $work->wordCount,
                    'readingMinutes' => ReadingTime::minutesFor($work->wordCount),
                    'chapterCount' => $work->chapterCount,
                    'genres' => $work->genres,
                    'blocked' => $work->blocked,
                    'archived' => $work->archived,
                    'createdAt' => $work->createdAt,
                    'updatedAt' => $work->updatedAt,
                ],
                $page->works,
            ),
        ]);
    }
}
