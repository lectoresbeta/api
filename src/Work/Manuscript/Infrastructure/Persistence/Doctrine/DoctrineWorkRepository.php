<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * @extends DoctrineRepository<Work>
 */
final class DoctrineWorkRepository extends DoctrineRepository implements WorkRepository
{
    public function save(Work $work): void
    {
        $this->register($work);
    }

    public function ofId(WorkId $id): ?Work
    {
        return $this->repository()->find($id->value());
    }

    public function ofAuthor(AuthorId $authorId, ?WorkStatus $status = null): array
    {
        $criteria = ['authorId' => $authorId->value()];

        if (null !== $status) {
            $criteria['status'] = $status;
        }

        return array_values($this->repository()->findBy($criteria, ['updatedAt' => 'DESC']));
    }

    public function remove(Work $work): void
    {
        $this->forget($work);
    }

    protected function entityClass(): string
    {
        return Work::class;
    }
}
