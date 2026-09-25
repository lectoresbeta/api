<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterAccess;
use LectoresBeta\Work\Chapter\Application\Contract\ReadableChapters;
use LectoresBeta\Work\Chapter\Domain\Enum\ChapterVisibility;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\Service\WorkReadPolicy;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;

/**
 * Quién puede leer un capítulo, respondido para fuera (`FEAT-COM-036`).
 *
 * **Es la misma secuencia que `GetChapterHandler`**, y eso es lo que se
 * quería: la obra decide primero —un capítulo visible de una novela en
 * borrador sigue siendo un borrador— y después el capítulo pone lo suyo.
 * Si algún día las dos se separan, el error estará en esta clase y no
 * repartido por otro contexto.
 */
final readonly class ResolveReadableChapters implements ReadableChapters
{
    public function __construct(
        private ChapterRepository $chapters,
        private WorkRepository $works,
        private WorkReadPolicy $policy,
    ) {
    }

    public function workOf(string $chapterId): ?string
    {
        try {
            $chapter = $this->chapters->ofId(ChapterId::fromString($chapterId));
        } catch (InvalidValue) {
            return null;
        }

        return $chapter?->workId()->value();
    }

    public function of(
        string $chapterId,
        string $readerId,
        bool $readerIsOfAge,
        bool $isBetaReader,
    ): ?ChapterAccess {
        try {
            $chapter = $this->chapters->ofId(ChapterId::fromString($chapterId));
            $reader = AuthorId::fromString($readerId);
        } catch (InvalidValue) {
            return null;
        }

        if (null === $chapter) {
            return null;
        }

        $work = $this->works->ofId($chapter->workId());

        if (null === $work) {
            return null;
        }

        $isAuthor = $work->authorId()->equals($reader);
        $chapterIsOpen = ChapterVisibility::VISIBLE === $chapter->visibility() && !$chapter->isBlocked();

        return new ChapterAccess(
            $chapter->id()->value(),
            $work->id()->value(),
            $work->authorId()->value(),
            $this->policy->allows($work, $reader, $readerIsOfAge, $isBetaReader)
                // El autor lee lo suyo en cualquier estado, también lo que
                // tiene oculto; para el resto, un capítulo oculto o bloqueado
                // no está.
                && ($isAuthor || $chapterIsOpen),
        );
    }
}
