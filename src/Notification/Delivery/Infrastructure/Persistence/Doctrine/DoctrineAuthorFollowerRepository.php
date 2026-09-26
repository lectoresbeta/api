<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Notification\Delivery\Domain\Entity\AuthorFollower;
use LectoresBeta\Notification\Delivery\Domain\Repository\AuthorFollowerRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<AuthorFollower>
 */
final class DoctrineAuthorFollowerRepository extends DoctrineRepository implements AuthorFollowerRepository
{
    public function followersOf(RecipientId $authorId, ?string $after, int $limit): array
    {
        $query = $this->repository()->createQueryBuilder('f')
            ->select('f.followerId')
            ->where('f.authorId = :author')
            ->setParameter('author', $authorId->value())
            ->orderBy('f.followerId', 'ASC')
            ->setMaxResults($limit);

        if (null !== $after) {
            $query->andWhere('f.followerId > :after')->setParameter('after', $after);
        }

        /** @var list<array{followerId: string}> $rows */
        $rows = $query->getQuery()->getResult();

        return array_map(static fn (array $row): string => $row['followerId'], $rows);
    }

    public function between(RecipientId $followerId, RecipientId $authorId): ?AuthorFollower
    {
        return $this->repository()->find([
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

    protected function entityClass(): string
    {
        return AuthorFollower::class;
    }
}
