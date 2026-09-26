<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Referral\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Referral\Domain\Entity\Referral;
use LectoresBeta\Credits\Referral\Domain\Repository\ReferralRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<Referral>
 */
final class DoctrineReferralRepository extends DoctrineRepository implements ReferralRepository
{
    public function ofInvitee(UserId $inviteeId): ?Referral
    {
        return $this->repository()->find($inviteeId->value());
    }

    public function rewardedCountOf(UserId $inviterId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(r.inviteeId)')
            ->from(Referral::class, 'r')
            ->where('r.inviterId = :inviter')
            ->andWhere('r.rewardedAt IS NOT NULL')
            ->setParameter('inviter', $inviterId->value())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Referral $referral): void
    {
        $this->register($referral);
    }

    protected function entityClass(): string
    {
        return Referral::class;
    }
}
