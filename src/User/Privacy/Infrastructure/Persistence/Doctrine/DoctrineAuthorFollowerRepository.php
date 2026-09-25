<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Entity\AuthorFollower;
use LectoresBeta\User\Privacy\Domain\Repository\AuthorFollowerRepository;

/**
 * @extends DoctrineRepository<AuthorFollower>
 */
final class DoctrineAuthorFollowerRepository extends DoctrineRepository implements AuthorFollowerRepository
{
    public function follows(UserId $followerId, UserId $authorId): bool
    {
        return null !== $this->between($followerId, $authorId);
    }

    public function followedAmong(UserId $followerId, array $authorIds): array
    {
        if ([] === $authorIds) {
            return [];
        }

        /** @var list<array{authorId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('f.authorId')
            ->from(AuthorFollower::class, 'f')
            ->where('f.followerId = :follower')
            ->andWhere('f.authorId IN (:authors)')
            ->setParameter('follower', $followerId->value())
            ->setParameter('authors', $authorIds)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'authorId');
    }

    public function between(UserId $followerId, UserId $authorId): ?AuthorFollower
    {
        return $this->repository()->findOneBy([
            'authorId' => $authorId->value(),
            'followerId' => $followerId->value(),
        ]);
    }

    public function save(AuthorFollower $follower): void
    {
        $this->register($follower);
    }

    public function remove(AuthorFollower $follower): void
    {
        $this->forget($follower);
    }

    public function countFollowersOf(UserId $authorId): int
    {
        return $this->repository()->count(['authorId' => $authorId->value()]);
    }

    public function countFollowedBy(UserId $followerId): int
    {
        return $this->repository()->count(['followerId' => $followerId->value()]);
    }

    protected function entityClass(): string
    {
        return AuthorFollower::class;
    }
}
