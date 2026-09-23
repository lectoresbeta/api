<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimStatus;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<Claim>
 */
final class DoctrineClaimRepository extends DoctrineRepository implements ClaimRepository
{
    public function save(Claim $claim): void
    {
        $this->register($claim);
    }

    public function ofId(ClaimId $id): ?Claim
    {
        return $this->repository()->find($id->value());
    }

    public function pending(int $limit = 50): array
    {
        /** @var list<Claim> $claims */
        $claims = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Claim::class, 'c')
            ->where('c.status IN (:open)')
            ->setParameter('open', [ClaimStatus::PENDING, ClaimStatus::UNDER_REVIEW])
            ->orderBy('c.submittedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $claims;
    }

    public function countFiledSince(PartyId $reporterId, \DateTimeImmutable $since): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Claim::class, 'c')
            ->where('c.reporterId = :reporter')
            ->andWhere('c.submittedAt >= :since')
            ->setParameter('reporter', $reporterId->value())
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function alreadyFiled(PartyId $reporterId, ClaimTargetType $targetType, string $targetId): bool
    {
        return $this->repository()->count([
            'reporterId' => $reporterId->value(),
            'targetType' => $targetType,
            'targetId' => $targetId,
        ]) > 0;
    }

    public function about(ClaimTargetType $targetType, string $targetId): array
    {
        return array_values($this->repository()->findBy(
            ['targetType' => $targetType, 'targetId' => $targetId],
            ['submittedAt' => 'DESC'],
        ));
    }

    protected function entityClass(): string
    {
        return Claim::class;
    }
}
