<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Curation\Domain\Entity\SavedPost;
use LectoresBeta\Community\Curation\Domain\Repository\SavedPostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<SavedPost>
 */
final class DoctrineSavedPostRepository extends DoctrineRepository implements SavedPostRepository
{
    public function save(SavedPost $saved): void
    {
        $this->register($saved);
    }

    public function remove(MemberId $memberId, PostId $postId): void
    {
        $saved = $this->repository()->find([
            'memberId' => $memberId->value(),
            'postId' => $postId->value(),
        ]);

        if (null !== $saved) {
            $this->forget($saved);
        }
    }

    public function has(MemberId $memberId, PostId $postId): bool
    {
        return null !== $this->repository()->find([
            'memberId' => $memberId->value(),
            'postId' => $postId->value(),
        ]);
    }

    public function savedBy(MemberId $memberId): array
    {
        /** @var list<array{postId: string}> $rows */
        $rows = $this->repository()->createQueryBuilder('s')
            ->select('s.postId AS postId')
            ->where('s.memberId = :member')
            ->setParameter('member', $memberId->value())
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $row): string => $row['postId'], $rows);
    }

    protected function entityClass(): string
    {
        return SavedPost::class;
    }
}
