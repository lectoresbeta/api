<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Curation\Domain\Entity\MutedMember;
use LectoresBeta\Community\Curation\Domain\Repository\MutedMemberRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<MutedMember>
 */
final class DoctrineMutedMemberRepository extends DoctrineRepository implements MutedMemberRepository
{
    public function save(MutedMember $muted): void
    {
        $this->register($muted);
    }

    public function remove(MemberId $memberId, MemberId $mutedId): void
    {
        $muted = $this->repository()->find([
            'memberId' => $memberId->value(),
            'mutedId' => $mutedId->value(),
        ]);

        if (null !== $muted) {
            $this->forget($muted);
        }
    }

    public function has(MemberId $memberId, MemberId $mutedId): bool
    {
        return null !== $this->repository()->find([
            'memberId' => $memberId->value(),
            'mutedId' => $mutedId->value(),
        ]);
    }

    public function mutedBy(MemberId $memberId): array
    {
        /** @var list<array{mutedId: string}> $rows */
        $rows = $this->repository()->createQueryBuilder('m')
            ->select('m.mutedId AS mutedId')
            ->where('m.memberId = :member')
            ->setParameter('member', $memberId->value())
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $row): string => $row['mutedId'], $rows);
    }

    public function pageOf(MemberId $memberId, ?Cursor $after, int $limit): array
    {
        $builder = $this->repository()->createQueryBuilder('m')
            ->where('m.memberId = :member')
            ->setParameter('member', $memberId->value())
            ->orderBy('m.mutedAt', 'DESC')
            ->addOrderBy('m.mutedId', 'DESC')
            // Una de más para saber si hay página siguiente.
            ->setMaxResults($limit + 1);

        if (null !== $after) {
            $builder
                ->andWhere('(m.mutedAt < :at OR (m.mutedAt = :at AND m.mutedId < :id))')
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<MutedMember> */
        return $builder->getQuery()->getResult();
    }

    protected function entityClass(): string
    {
        return MutedMember::class;
    }
}
