<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Entity\BetaReaderGroupMember;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupMemberRepository;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<BetaReaderGroupMember>
 */
final class DoctrineBetaReaderGroupMemberRepository extends DoctrineRepository implements BetaReaderGroupMemberRepository
{
    public function save(BetaReaderGroupMember $member): void
    {
        $this->register($member);
    }

    public function remove(BetaReaderGroupId $groupId, ReaderId $readerId): void
    {
        $member = $this->repository()->find([
            'groupId' => $groupId->value(),
            'readerId' => $readerId->value(),
        ]);

        if (null !== $member) {
            $this->forget($member);
        }
    }

    public function removeAllOf(BetaReaderGroupId $groupId): void
    {
        foreach ($this->of($groupId) as $member) {
            $this->forget($member);
        }
    }

    public function has(BetaReaderGroupId $groupId, ReaderId $readerId): bool
    {
        return null !== $this->repository()->find([
            'groupId' => $groupId->value(),
            'readerId' => $readerId->value(),
        ]);
    }

    public function of(BetaReaderGroupId $groupId): array
    {
        /** @var list<BetaReaderGroupMember> */
        return $this->repository()->createQueryBuilder('m')
            ->where('m.groupId = :group')
            ->setParameter('group', $groupId->value())
            ->orderBy('m.addedAt', 'ASC')
            ->addOrderBy('m.readerId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countIn(BetaReaderGroupId $groupId): int
    {
        return (int) $this->repository()->createQueryBuilder('m')
            ->select('COUNT(m.readerId)')
            ->where('m.groupId = :group')
            ->setParameter('group', $groupId->value())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countsOf(array $groupIds): array
    {
        if ([] === $groupIds) {
            return [];
        }

        /** @var list<array{groupId: string, total: int|string}> $rows */
        $rows = $this->repository()->createQueryBuilder('m')
            ->select('m.groupId AS groupId, COUNT(m.readerId) AS total')
            ->where('m.groupId IN (:groups)')
            ->setParameter('groups', array_map(
                static fn (BetaReaderGroupId $id): string => $id->value(),
                $groupIds,
            ))
            ->groupBy('m.groupId')
            ->getQuery()
            ->getResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['groupId']] = (int) $row['total'];
        }

        return $counts;
    }

    protected function entityClass(): string
    {
        return BetaReaderGroupMember::class;
    }
}
