<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Entity\Award;
use LectoresBeta\User\AuthorPage\Domain\Repository\AwardRepository;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\AwardId;

/**
 * @extends DoctrineRepository<Award>
 */
final class DoctrineAwardRepository extends DoctrineRepository implements AwardRepository
{
    public function save(Award $award): void
    {
        $this->register($award);
    }

    public function remove(Award $award): void
    {
        $this->forget($award);
    }

    public function ofId(AwardId $id): ?Award
    {
        return $this->repository()->find($id->value());
    }

    public function ofAuthor(UserId $userId): array
    {
        /**
         * `RN-8`: año descendente, y los que no llevan año al final.
         *
         * El `CASE` está porque PostgreSQL ordena los nulos primero en
         * descendente y DQL no tiene `NULLS LAST`. Sin él, los premios sin
         * año encabezarían la lista, que es exactamente lo contrario de lo
         * que dice la regla: «no sé de cuándo es» ordena peor que cualquier
         * fecha.
         *
         * @var list<Award>
         */
        return $this->repository()->createQueryBuilder('a')
            ->addSelect('CASE WHEN a.year IS NULL THEN 1 ELSE 0 END AS HIDDEN undated')
            ->where('a.userId = :user')
            ->setParameter('user', $userId->value())
            ->orderBy('undated', 'ASC')
            ->addOrderBy('a.year', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countOfAuthor(UserId $userId): int
    {
        return (int) $this->repository()->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.userId = :user')
            ->setParameter('user', $userId->value())
            ->getQuery()
            ->getSingleScalarResult();
    }

    protected function entityClass(): string
    {
        return Award::class;
    }
}
