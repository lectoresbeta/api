<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterHeading;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterHeadings;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;

final readonly class ResolveChapterHeadings implements ChapterHeadings
{
    public function __construct(
        private ChapterRepository $chapters,
        private WorkRepository $works,
    ) {
    }

    public function ofChapters(array $chapterIds): array
    {
        $chapters = [];

        foreach (array_unique($chapterIds) as $id) {
            try {
                $chapter = $this->chapters->ofId(ChapterId::fromString($id));
            } catch (InvalidValue) {
                continue;
            }

            if (null !== $chapter) {
                $chapters[$id] = $chapter;
            }
        }

        if ([] === $chapters) {
            return [];
        }

        $works = $this->works->ofIds(array_values(array_unique(
            array_map(static fn ($chapter) => $chapter->workId(), $chapters),
            \SORT_REGULAR,
        )));

        $headings = [];

        foreach ($chapters as $id => $chapter) {
            $work = $works[$chapter->workId()->value()] ?? null;

            $headings[$id] = new ChapterHeading(
                $id,
                $chapter->workId()->value(),
                $chapter->title(),
                $chapter->position(),
                null === $work ? '' : (string) $work->title(),
            );
        }

        return $headings;
    }
}
