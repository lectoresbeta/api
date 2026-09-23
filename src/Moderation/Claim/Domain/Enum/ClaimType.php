<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\Enum;

enum ClaimType: string
{
    case INAPPROPRIATE_WORK = 'INAPPROPRIATE_WORK';
    case INAPPROPRIATE_CHAPTER = 'INAPPROPRIATE_CHAPTER';
    case FRAUDULENT_FEEDBACK = 'FRAUDULENT_FEEDBACK';
    case ABUSIVE_USER = 'ABUSIVE_USER';
    case MISLABELLED_CONTENT = 'MISLABELLED_CONTENT';

    /**
     * Upholding these reverses the credits of a correction: two new
     * movements, never an edit of the original (`decision:0006` `RN-2`).
     */
    public function reversesCredits(): bool
    {
        return self::FRAUDULENT_FEEDBACK === $this;
    }
}
