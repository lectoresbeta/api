<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ContentReview\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\ContentReview\Domain\Entity\ContentReview;
use LectoresBeta\Moderation\ContentReview\Domain\Repository\ContentReviewRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<ContentReview>
 */
final class DoctrineContentReviewRepository extends DoctrineRepository implements ContentReviewRepository
{
    public function save(ContentReview $review): void
    {
        $this->register($review);
    }

    public function historyOf(ClaimTargetType $targetType, string $targetId): array
    {
        return array_values($this->repository()->findBy(
            ['targetType' => $targetType, 'targetId' => $targetId],
            ['reviewedAt' => 'DESC'],
        ));
    }

    public function wasReviewed(ClaimTargetType $targetType, string $targetId, string $contentHash): bool
    {
        return $this->repository()->count([
            'targetType' => $targetType,
            'targetId' => $targetId,
            'contentHash' => $contentHash,
        ]) > 0;
    }

    protected function entityClass(): string
    {
        return ContentReview::class;
    }
}
