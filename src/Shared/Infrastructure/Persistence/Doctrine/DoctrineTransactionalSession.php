<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

final readonly class DoctrineTransactionalSession implements TransactionalSession
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function execute(callable $operation): mixed
    {
        return $this->entityManager->wrapInTransaction($operation);
    }
}
