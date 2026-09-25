<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Chapter\Domain\Entity\ChapterVersion;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterVersionRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;

/**
 * @extends DoctrineRepository<ChapterVersion>
 */
final class DoctrineChapterVersionRepository extends DoctrineRepository implements ChapterVersionRepository
{
    public function add(ChapterVersion $version): void
    {
        $this->register($version);
    }

    public function ofChapterVersion(ChapterId $chapterId, int $version): ?ChapterVersion
    {
        return $this->repository()->findOneBy([
            'chapterId' => $chapterId->value(),
            'version' => $version,
        ]);
    }

    protected function entityClass(): string
    {
        return ChapterVersion::class;
    }
}
