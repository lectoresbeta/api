<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ContentReview\Domain\Enum;

enum ContentReviewOutcome: string
{
    case PASSED = 'PASSED';
    case FLAGGED = 'FLAGGED';
}
