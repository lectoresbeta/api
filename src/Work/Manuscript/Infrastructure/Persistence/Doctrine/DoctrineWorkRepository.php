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

    public function pageOfAuthor(
        AuthorId $authorId,
        ?WorkStatus $status,
        bool $oldestFirst,
        int $limit,
        int $offset,
    ): array {
        $query = $this->authored($authorId, $status)
            ->select('w')
            ->orderBy('w.createdAt', $oldestFirst ? 'ASC' : 'DESC')
            // El desempate va en la misma dirección que el criterio. Dos
            // obras creadas en el mismo segundo son lo normal al escribir, y
            // un desempate fijo haría que «más antiguos» devolviera la más
            // nueva primero — un orden que se contradice a sí mismo.
            ->addOrderBy('w.id', $oldestFirst ? 'ASC' : 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return array_values($query->getQuery()->getResult());
    }

    public function countOfAuthor(AuthorId $authorId, ?WorkStatus $status): int
    {
        return (int) $this->authored($authorId, $status)
            ->select('COUNT(w.id)')
            ->getQuery()
            ->getSingleScalarResult();
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

    private function authored(AuthorId $authorId, ?WorkStatus $status): \Doctrine\ORM\QueryBuilder
    {
        $query = $this->repository()->createQueryBuilder('w')
            ->where('w.authorId = :author')
            ->setParameter('author', $authorId->value());

        if (null !== $status) {
            $query->andWhere('w.status = :status')->setParameter('status', $status);
        }

        return $query;
    }
}
