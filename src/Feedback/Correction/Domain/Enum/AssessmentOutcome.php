<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Enum;

enum AssessmentOutcome: string
{
    case PASSED = 'PASSED';
    case FLAGGED = 'FLAGGED';
    case REJECTED = 'REJECTED';
}
