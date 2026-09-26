<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Feedback\Correction\Domain\Entity\FrozenDebt;
use LectoresBeta\Feedback\Correction\Domain\Repository\FrozenDebtRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<FrozenDebt>
 */
final class DoctrineFrozenDebtRepository extends DoctrineRepository implements FrozenDebtRepository
{
    public function save(FrozenDebt $frozen): void
    {
        $this->register($frozen);
    }

    public function ofAuthor(AuthorId $authorId): ?FrozenDebt
    {
        return $this->repository()->find($authorId->value());
    }

    protected function entityClass(): string
    {
        return FrozenDebt::class;
    }
}
