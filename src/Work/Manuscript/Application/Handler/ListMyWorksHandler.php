<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Manuscript\Application\DTO\MyWork;
use LectoresBeta\Work\Manuscript\Application\DTO\MyWorksPage;
use LectoresBeta\Work\Manuscript\Application\Query\ListMyWorks;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;
use LectoresBeta\Work\Manuscript\Domain\Exception\UnsupportedSort;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkGenreRepository;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;

/**
 * «Mis relatos» (`FEAT-WRK-015`).
 *
 * **Solo las obras de quien pregunta** (`RN-1`). Parece obvia y es la regla
 * que hay que probar: un fallo aquí expone los borradores de otro autor, que
 * es obra inédita que nadie ha decidido enseñar.
 *
 * Es el único listado que **incluye los borradores** (`RN-2`), y el único que
 * lleva la insignia de estado: es información de gestión, no del catálogo.
 *
 * Y una obra **archivada** también aparece, marcada. Para el resto del mundo
 * no existe, pero su autor tiene que poder encontrarla — si no, «recuperar»
 * sería una operación sin pantalla desde la que pedirla.
 */
final readonly class ListMyWorksHandler
{
    private const MAX_PER_PAGE = 50;

    /** `RN-4` del contrato: un `sort` no admitido no se ignora en silencio. */
    private const SORTS = ['recent', 'oldest'];

    public function __construct(
        private WorkRepository $works,
        private WorkGenreRepository $genres,
    ) {
    }

    public function __invoke(ListMyWorks $query): MyWorksPage
    {
        $sort = $query->sort ?? 'recent';

        if (!\in_array($sort, self::SORTS, true)) {
            throw UnsupportedSort::of($sort, self::SORTS);
        }

        $status = null === $query->status ? null : WorkStatus::tryFrom(strtoupper($query->status));

        if (null !== $query->status && null === $status) {
            throw UnsupportedSort::of($query->status, array_column(WorkStatus::cases(), 'value'));
        }

        try {
            $authorId = AuthorId::fromString($query->authorId);
        } catch (InvalidValue) {
            return new MyWorksPage([], 0, 1, self::MAX_PER_PAGE);
        }

        $page = max(1, $query->page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $query->perPage));

        $works = $this->works->pageOfAuthor($authorId, $status, 'oldest' === $sort, $perPage, ($page - 1) * $perPage);
        $genres = $this->genres->codesOfWorks(array_map(
            static fn (Work $work) => $work->id(),
            $works,
        ));

        return new MyWorksPage(
            array_map(
                static fn (Work $work): MyWork => new MyWork(
                    $work->id()->value(),
                    (string) $work->title(),
                    $work->synopsis(),
                    $work->status()->value,
                    $work->accessMode()->value,
                    $work->isAdultsOnly(),
                    $work->wordCount(),
                    $work->chapterCount(),
                    $genres[$work->id()->value()] ?? [],
                    $work->isBlocked(),
                    $work->isArchived(),
                    $work->createdAt()->format(\DATE_ATOM),
                    $work->updatedAt()->format(\DATE_ATOM),
                ),
                $works,
            ),
            $this->works->countOfAuthor($authorId, $status),
            $page,
            $perPage,
        );
    }
}
