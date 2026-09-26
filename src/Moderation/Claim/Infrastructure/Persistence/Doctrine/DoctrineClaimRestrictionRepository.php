<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Moderation\Claim\Domain\Entity\ClaimRestriction;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRestrictionRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<ClaimRestriction>
 */
final class DoctrineClaimRestrictionRepository extends DoctrineRepository implements ClaimRestrictionRepository
{
    public function save(ClaimRestriction $restriction): void
    {
        $this->register($restriction);
    }

    public function ofUser(PartyId $userId): ?ClaimRestriction
    {
        return $this->repository()->find($userId->value());
    }

    protected function entityClass(): string
    {
        return ClaimRestriction::class;
    }
}
