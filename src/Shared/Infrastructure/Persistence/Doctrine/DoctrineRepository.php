<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * What every Doctrine repository in the project shares.
 *
 * It is deliberately thin. A base class that grew query helpers would end up
 * being the place where business rules hide, which is the opposite of what
 * repositories are for.
 *
 * Note what it does **not** do: it never flushes. Deciding when a transaction
 * closes belongs to the use case (`TransactionalSession`), and a repository
 * that flushes on every save makes that impossible.
 *
 * @template TEntity of object
 */
abstract class DoctrineRepository
{
    public function __construct(protected readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return class-string<TEntity>
     */
    abstract protected function entityClass(): string;

    /**
     * @return EntityRepository<TEntity>
     */
    protected function repository(): EntityRepository
    {
        return $this->entityManager->getRepository($this->entityClass());
    }

    /**
     * @param TEntity $entity
     */
    protected function register(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    /**
     * @param TEntity $entity
     */
    protected function forget(object $entity): void
    {
        $this->entityManager->remove($entity);
    }
}
