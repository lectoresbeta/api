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

    public function ofIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $found = [];

        foreach ($this->repository()->findBy(['id' => array_map(
            static fn (WorkId $id): string => $id->value(),
            $ids,
        )]) as $work) {
            $found[$work->id()->value()] = $work;
        }

        return $found;
    }

    public function ofAuthor(AuthorId $authorId, ?WorkStatus $status = null): array
    {
        $criteria = ['authorId' => $authorId->value()];

        if (null !== $status) {
            $criteria['status'] = $status;
        }

        return array_values($this->repository()->findBy($criteria, ['updatedAt' => 'DESC']));
    }

    public function countByAuthor(AuthorId $authorId): int
    {
        // Las archivadas no cuentan (`FEAT-WRK-006` `RN-10`): para el resto
        // del mundo no existen, y un contador que las incluyera prometería
        // obras que nadie va a poder abrir.
        return (int) $this->repository()->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.authorId = :author')
            ->andWhere('w.archivedAt IS NULL')
            ->setParameter('author', $authorId->value())
            ->getQuery()
            ->getSingleScalarResult();
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
