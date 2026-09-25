<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Repository;

use LectoresBeta\Moderation\Review\Domain\Entity\ClaimReview;

interface ClaimReviewRepository
{
    public function add(ClaimReview $review): void;
}
