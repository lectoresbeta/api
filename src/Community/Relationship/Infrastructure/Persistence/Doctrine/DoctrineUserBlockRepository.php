<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Relationship\Domain\Entity\UserBlock;
use LectoresBeta\Community\Relationship\Domain\Repository\UserBlockRepository;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<UserBlock>
 */
final class DoctrineUserBlockRepository extends DoctrineRepository implements UserBlockRepository
{
    public function between(MemberId $blockerId, MemberId $blockedId): ?UserBlock
    {
        return $this->repository()->findOneBy([
            'blockerId' => $blockerId->value(),
            'blockedId' => $blockedId->value(),
        ]);
    }

    public function existsBetween(MemberId $one, MemberId $other): bool
    {
        return $this->repository()->count([
            'blockerId' => $one->value(),
            'blockedId' => $other->value(),
        ]) > 0 || $this->repository()->count([
            'blockerId' => $other->value(),
            'blockedId' => $one->value(),
        ]) > 0;
    }

    public function involving(MemberId $member): array
    {
        /** @var list<array{blockerId: string, blockedId: string}> $rows */
        $rows = $this->repository()->createQueryBuilder('b')
            ->select('b.blockerId', 'b.blockedId')
            ->where('b.blockerId = :member OR b.blockedId = :member')
            ->setParameter('member', $member->value())
            ->getQuery()
            ->getResult();

        $others = [];

        foreach ($rows as $row) {
            $other = $row['blockerId'] === $member->value() ? $row['blockedId'] : $row['blockerId'];
            $others[$other] = true;
        }

        return array_keys($others);
    }

    public function blockedBy(MemberId $blockerId, ?Cursor $after, int $limit): array
    {
        $query = $this->repository()->createQueryBuilder('b')
            ->where('b.blockerId = :blocker')
            ->setParameter('blocker', $blockerId->value());

        if (null !== $after) {
            $query
                ->andWhere('(b.createdAt < :at OR (b.createdAt = :at AND b.id < :id))')
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<UserBlock> $found */
        $found = $query
            ->orderBy('b.createdAt', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return $found;
    }

    public function save(UserBlock $block): void
    {
        $this->register($block);
    }

    public function remove(UserBlock $block): void
    {
        $this->forget($block);
    }

    protected function entityClass(): string
    {
        return UserBlock::class;
    }
}
