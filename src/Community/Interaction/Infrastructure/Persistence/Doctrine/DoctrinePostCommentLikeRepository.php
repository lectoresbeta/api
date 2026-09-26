<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Interaction\Domain\Entity\PostCommentLike;
use LectoresBeta\Community\Interaction\Domain\Repository\PostCommentLikeRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<PostCommentLike>
 */
final class DoctrinePostCommentLikeRepository extends DoctrineRepository implements PostCommentLikeRepository
{
    public function save(PostCommentLike $like): void
    {
        $this->register($like);
    }

    public function remove(PostCommentLike $like): void
    {
        $this->forget($like);
    }

    public function between(MemberId $memberId, PostCommentId $commentId): ?PostCommentLike
    {
        return $this->repository()->find([
            'commentId' => $commentId->value(),
            'memberId' => $memberId->value(),
        ]);
    }

    public function likedAmong(MemberId $memberId, array $commentIds): array
    {
        if ([] === $commentIds) {
            return [];
        }

        /** @var list<array{commentId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('l.commentId AS commentId')
            ->from(PostCommentLike::class, 'l')
            ->where('l.memberId = :member')
            ->andWhere('l.commentId IN (:comments)')
            ->setParameter('member', $memberId->value())
            ->setParameter('comments', $commentIds)
            ->getQuery()
            ->getResult();

        return array_values(array_map(static fn (array $row): string => $row['commentId'], $rows));
    }

    public function removeAllOf(PostCommentId $commentId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(PostCommentLike::class, 'l')
            ->where('l.commentId = :comment')
            ->setParameter('comment', $commentId->value())
            ->getQuery()
            ->execute();
    }

    protected function entityClass(): string
    {
        return PostCommentLike::class;
    }
}
