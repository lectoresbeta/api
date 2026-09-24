<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Manuscript\Domain\Entity\WorkGenre;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkGenreRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * @extends DoctrineRepository<WorkGenre>
 */
final class DoctrineWorkGenreRepository extends DoctrineRepository implements WorkGenreRepository
{
    public function replaceAll(WorkId $workId, array $codes): void
    {
        foreach ($this->rowsOf($workId) as $genre) {
            $this->forget($genre);
        }

        foreach ($codes as $code) {
            $this->register(new WorkGenre($workId, $code));
        }
    }

    public function codesOf(WorkId $workId): array
    {
        return array_map(static fn (WorkGenre $genre): string => $genre->genreCode(), $this->rowsOf($workId));
    }

    protected function entityClass(): string
    {
        return WorkGenre::class;
    }

    /**
     * @return list<WorkGenre>
     */
    private function rowsOf(WorkId $workId): array
    {
        return array_values($this->repository()->findBy(['workId' => $workId->value()], ['genreCode' => 'ASC']));
    }
}
