<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Curation\Domain\Entity\HiddenPost;
use LectoresBeta\Community\Curation\Domain\Repository\HiddenPostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<HiddenPost>
 */
final class DoctrineHiddenPostRepository extends DoctrineRepository implements HiddenPostRepository
{
    public function save(HiddenPost $hidden): void
    {
        $this->register($hidden);
    }

    public function remove(MemberId $memberId, PostId $postId): void
    {
        $hidden = $this->repository()->find([
            'memberId' => $memberId->value(),
            'postId' => $postId->value(),
        ]);

        if (null !== $hidden) {
            $this->forget($hidden);
        }
    }

    public function hiddenBy(MemberId $memberId): array
    {
        /** @var list<array{postId: string}> $rows */
        $rows = $this->repository()->createQueryBuilder('h')
            ->select('h.postId AS postId')
            ->where('h.memberId = :member')
            ->setParameter('member', $memberId->value())
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $row): string => $row['postId'], $rows);
    }

    protected function entityClass(): string
    {
        return HiddenPost::class;
    }
}
