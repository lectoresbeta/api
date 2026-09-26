<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Entity\BetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupRepository;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupName;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<BetaReaderGroup>
 */
final class DoctrineBetaReaderGroupRepository extends DoctrineRepository implements BetaReaderGroupRepository
{
    public function save(BetaReaderGroup $group): void
    {
        $this->register($group);
    }

    public function delete(BetaReaderGroup $group): void
    {
        $this->forget($group);
    }

    public function ofId(BetaReaderGroupId $id): ?BetaReaderGroup
    {
        return $this->repository()->find($id->value());
    }

    public function ofAuthor(AuthorId $authorId, ?string $query): array
    {
        $builder = $this->repository()->createQueryBuilder('g')
            ->where('g.authorId = :author')
            ->setParameter('author', $authorId->value())
            ->orderBy('LOWER(g.name)', 'ASC')
            ->addOrderBy('g.id', 'ASC');

        if (null !== $query) {
            $builder
                ->andWhere('LOWER(g.name) LIKE :query')
                ->setParameter('query', '%'.self::escaped(mb_strtolower($query)).'%');
        }

        /** @var list<BetaReaderGroup> */
        return $builder->getQuery()->getResult();
    }

    public function countOfAuthor(AuthorId $authorId): int
    {
        return (int) $this->repository()->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.authorId = :author')
            ->setParameter('author', $authorId->value())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function nameIsTaken(AuthorId $authorId, BetaReaderGroupName $name, ?BetaReaderGroupId $except): bool
    {
        $builder = $this->repository()->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.authorId = :author')
            ->andWhere('LOWER(g.name) = :name')
            ->setParameter('author', $authorId->value())
            ->setParameter('name', $name->comparable());

        if (null !== $except) {
            $builder->andWhere('g.id <> :except')->setParameter('except', $except->value());
        }

        return (int) $builder->getQuery()->getSingleScalarResult() > 0;
    }

    protected function entityClass(): string
    {
        return BetaReaderGroup::class;
    }

    /**
     * Un nombre con `%` o `_` es un nombre, no un comodín. Sin esto, buscar
     * «_» devolvería todos los grupos del autor.
     */
    private static function escaped(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }
}
