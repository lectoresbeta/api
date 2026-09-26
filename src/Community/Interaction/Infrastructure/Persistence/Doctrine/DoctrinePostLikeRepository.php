<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Interaction\Domain\Entity\PostLike;
use LectoresBeta\Community\Interaction\Domain\Repository\PostLikeRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<PostLike>
 */
final class DoctrinePostLikeRepository extends DoctrineRepository implements PostLikeRepository
{
    public function save(PostLike $like): void
    {
        $this->register($like);
    }

    public function remove(PostLike $like): void
    {
        $this->forget($like);
    }

    public function between(MemberId $memberId, PostId $postId): ?PostLike
    {
        return $this->repository()->find([
            'postId' => $postId->value(),
            'memberId' => $memberId->value(),
        ]);
    }

    public function likedAmong(MemberId $memberId, array $postIds): array
    {
        if ([] === $postIds) {
            return [];
        }

        /** @var list<array{postId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('l.postId AS postId')
            ->from(PostLike::class, 'l')
            ->where('l.memberId = :member')
            ->andWhere('l.postId IN (:posts)')
            ->setParameter('member', $memberId->value())
            ->setParameter('posts', $postIds)
            ->getQuery()
            ->getResult();

        return array_values(array_map(static fn (array $row): string => $row['postId'], $rows));
    }

    public function removeAllOf(PostId $postId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(PostLike::class, 'l')
            ->where('l.postId = :post')
            ->setParameter('post', $postId->value())
            ->getQuery()
            ->execute();
    }

    protected function entityClass(): string
    {
        return PostLike::class;
    }
}
