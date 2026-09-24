<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Entity\BlockedPair;
use LectoresBeta\User\Privacy\Domain\Repository\BlockedPairRepository;

/**
 * @extends DoctrineRepository<BlockedPair>
 */
final class DoctrineBlockedPairRepository extends DoctrineRepository implements BlockedPairRepository
{
    public function exists(UserId $one, UserId $other): bool
    {
        return null !== $this->between($one, $other);
    }

    public function between(UserId $one, UserId $other): ?BlockedPair
    {
        [$first, $second] = BlockedPair::ordered($one->value(), $other->value());

        return $this->repository()->findOneBy(['oneId' => $first, 'otherId' => $second]);
    }

    public function save(BlockedPair $pair): void
    {
        $this->register($pair);
    }

    public function remove(BlockedPair $pair): void
    {
        $this->forget($pair);
    }

    protected function entityClass(): string
    {
        return BlockedPair::class;
    }
}
