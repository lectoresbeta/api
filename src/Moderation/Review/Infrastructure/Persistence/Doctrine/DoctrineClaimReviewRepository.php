<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Moderation\Review\Domain\Entity\ClaimReview;
use LectoresBeta\Moderation\Review\Domain\Repository\ClaimReviewRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<ClaimReview>
 */
final class DoctrineClaimReviewRepository extends DoctrineRepository implements ClaimReviewRepository
{
    public function add(ClaimReview $review): void
    {
        $this->register($review);
    }

    protected function entityClass(): string
    {
        return ClaimReview::class;
    }
}
