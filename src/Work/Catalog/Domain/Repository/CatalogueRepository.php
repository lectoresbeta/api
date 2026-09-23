<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalog\Domain\Repository;

use LectoresBeta\Work\Catalog\Domain\Entity\CatalogueEntry;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * The read model behind «Leer» (`decision:0008`).
 *
 * `mostNeglected` is where the ordering of that decision actually happens:
 * capacity × neglect × freshness, computed in SQL. The formula is stated once
 * in `CatalogueScore`, which is what the tests check; this is the copy the
 * database runs.
 */
interface CatalogueRepository
{
    public function save(CatalogueEntry $entry): void;

    public function ofChapter(ChapterId $chapterId): ?CatalogueEntry;

    /**
     * @param list<string> $excludedGenres
     * @param list<string> $excludedWarnings
     *
     * @return list<CatalogueEntry>
     */
    public function mostNeglected(
        \DateTimeImmutable $now,
        AuthorId $reader,
        array $excludedGenres = [],
        array $excludedWarnings = [],
        bool $allowAdultsOnly = true,
        int $limit = 20,
    ): array;

    public function removeWork(WorkId $workId): void;
}
