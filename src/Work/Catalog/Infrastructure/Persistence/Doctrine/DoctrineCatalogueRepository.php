<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalog\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Catalog\Domain\Entity\CatalogueEntry;
use LectoresBeta\Work\Catalog\Domain\Repository\CatalogueRepository;
use LectoresBeta\Work\Catalog\Domain\Service\CatalogueScore;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * @extends DoctrineRepository<CatalogueEntry>
 */
final class DoctrineCatalogueRepository extends DoctrineRepository implements CatalogueRepository
{
    /**
     * The three factors of `decision:0008`, in SQL.
     *
     * Native SQL and not DQL: the ordering needs `@>` over JSONB and the GIN
     * indexes behind it, and neither survives DQL. It is confined to
     * Infrastructure, which is exactly where PostgreSQL-specific code belongs.
     */
    private const RANKING_SQL = <<<'SQL'
        SELECT chapter_id
          FROM work_ctx.catalogue_entry
         WHERE correctable
           AND open_corrections < :maxOpen
           AND author_id <> :reader
           AND (:allowAdultsOnly OR NOT adults_only)
           AND NOT (genres @> :excludedGenres::jsonb)
           AND NOT (content_warnings @> :excludedWarnings::jsonb)
         ORDER BY
             LEAST(author_balance::numeric / GREATEST(price, 1), :maxCapacity)
           * (1.0 / (1 + corrections_received))
           * (1.0 / POWER(1 + EXTRACT(EPOCH FROM (:now - opened_at)) / 604800.0, 0.5))
           DESC,
           chapter_id DESC
         LIMIT :maxResults
        SQL;

    /**
     * Three simultaneous corrections per chapter (`C-41`). It is what caps
     * the overdraft caused by a race without setting any credits aside.
     */
    private const MAX_OPEN_CORRECTIONS = 3;

    public function save(CatalogueEntry $entry): void
    {
        $this->register($entry);
    }

    public function ofChapter(ChapterId $chapterId): ?CatalogueEntry
    {
        return $this->repository()->find($chapterId->value());
    }

    public function mostNeglected(
        \DateTimeImmutable $now,
        AuthorId $reader,
        array $excludedGenres = [],
        array $excludedWarnings = [],
        bool $allowAdultsOnly = true,
        int $limit = 20,
    ): array {
        /** @var list<string> $chapterIds */
        $chapterIds = $this->entityManager->getConnection()->fetchFirstColumn(self::RANKING_SQL, [
            'maxOpen' => self::MAX_OPEN_CORRECTIONS,
            'reader' => $reader->value(),
            'allowAdultsOnly' => $allowAdultsOnly,
            'excludedGenres' => json_encode(array_values($excludedGenres), \JSON_THROW_ON_ERROR),
            'excludedWarnings' => json_encode(array_values($excludedWarnings), \JSON_THROW_ON_ERROR),
            'maxCapacity' => CatalogueScore::MAX_CAPACITY,
            'now' => $now->format('Y-m-d H:i:s'),
            'maxResults' => $limit,
        ]);

        if ([] === $chapterIds) {
            return [];
        }

        $entries = $this->repository()->findBy(['chapterId' => $chapterIds]);

        // findBy() loses the ordering, and the ordering is the whole point.
        $byId = [];
        foreach ($entries as $entry) {
            $byId[$entry->chapterId()->value()] = $entry;
        }

        return array_values(array_filter(array_map(
            static fn (string $id): ?CatalogueEntry => $byId[$id] ?? null,
            $chapterIds,
        )));
    }

    public function removeWork(WorkId $workId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(CatalogueEntry::class, 'e')
            ->where('e.workId = :workId')
            ->setParameter('workId', $workId->value())
            ->getQuery()
            ->execute();
    }

    protected function entityClass(): string
    {
        return CatalogueEntry::class;
    }
}
