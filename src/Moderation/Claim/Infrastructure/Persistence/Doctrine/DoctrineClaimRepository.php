<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
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

    public function of(PartyId $reporterId, ClaimTargetType $targetType, string $targetId): ?Claim
    {
        return $this->repository()->findOneBy([
            'reporterId' => $reporterId->value(),
            'targetType' => $targetType,
            'targetId' => $targetId,
        ]);
    }

    public function countBy(PartyId $reporterId, \DateTimeImmutable $since): int
    {
        /** @var int $count */
        $count = $this->repository()->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.reporterId = :reporter')
            ->andWhere('c.submittedAt >= :since')
            ->setParameter('reporter', $reporterId->value())
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    public function by(PartyId $reporterId): array
    {
        return array_values($this->repository()->findBy(
            ['reporterId' => $reporterId->value()],
            ['submittedAt' => 'DESC'],
        ));
    }

    protected function entityClass(): string
    {
        return Claim::class;
    }
}
