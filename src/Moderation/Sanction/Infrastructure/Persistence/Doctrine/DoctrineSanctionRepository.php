<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Sanction\Domain\Entity\Sanction;
use LectoresBeta\Moderation\Sanction\Domain\Repository\SanctionRepository;
use LectoresBeta\Moderation\Sanction\Domain\ValueObject\SanctionId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<Sanction>
 */
final class DoctrineSanctionRepository extends DoctrineRepository implements SanctionRepository
{
    public function save(Sanction $sanction): void
    {
        $this->register($sanction);
    }

    public function ofId(SanctionId $sanctionId): ?Sanction
    {
        return $this->repository()->find($sanctionId->value());
    }

    public function historyOf(PartyId $userId): array
    {
        /** @var list<Sanction> $found */
        $found = $this->repository()->createQueryBuilder('s')
            ->where('s.userId = :user')
            ->setParameter('user', $userId->value())
            ->orderBy('s.imposedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $found;
    }

    public function inForceFor(PartyId $userId, \DateTimeImmutable $moment): array
    {
        /** @var list<Sanction> $found */
        $found = $this->repository()->createQueryBuilder('s')
            ->where('s.userId = :user')
            ->andWhere('s.liftedAt IS NULL')
            ->andWhere('s.expiresAt IS NULL OR s.expiresAt > :moment')
            ->setParameter('user', $userId->value())
            ->setParameter('moment', $moment)
            ->orderBy('s.imposedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $found;
    }

    protected function entityClass(): string
    {
        return Sanction::class;
    }
}
