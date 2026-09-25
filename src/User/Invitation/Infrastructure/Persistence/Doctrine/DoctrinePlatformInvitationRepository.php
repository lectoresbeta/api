<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Invitation\Domain\Entity\PlatformInvitation;
use LectoresBeta\User\Invitation\Domain\Repository\PlatformInvitationRepository;
use LectoresBeta\User\Invitation\Domain\ValueObject\PlatformInvitationId;

/**
 * @extends DoctrineRepository<PlatformInvitation>
 */
final class DoctrinePlatformInvitationRepository extends DoctrineRepository implements PlatformInvitationRepository
{
    public function ofId(PlatformInvitationId $id): ?PlatformInvitation
    {
        return $this->repository()->find($id->value());
    }

    public function ofTokenHash(string $tokenHash): ?PlatformInvitation
    {
        return $this->repository()->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function sentBy(UserId $inviterId, int $limit, int $offset): array
    {
        return array_values($this->repository()->findBy(
            ['inviterId' => $inviterId->value()],
            ['createdAt' => 'DESC'],
            $limit,
            $offset,
        ));
    }

    public function sentCountSince(UserId $inviterId, \DateTimeImmutable $since): int
    {
        return (int) $this->repository()->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.inviterId = :inviter')
            ->andWhere('i.createdAt >= :since')
            ->setParameter('inviter', $inviterId->value())
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function liveTo(UserId $inviterId, string $email): ?PlatformInvitation
    {
        return $this->repository()->findOneBy([
            'inviterId' => $inviterId->value(),
            'email' => $email,
            'consumedAt' => null,
        ]);
    }

    public function save(PlatformInvitation $invitation): void
    {
        $this->register($invitation);
    }

    protected function entityClass(): string
    {
        return PlatformInvitation::class;
    }
}
