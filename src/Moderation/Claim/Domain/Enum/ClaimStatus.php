<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\Enum;

/**
 * `PENDING → UNDER_REVIEW → UPHELD | REJECTED`, and **never backwards**
 * (`RN-6`). A mistake is corrected with a new, reasoned action, not by
 * reopening the file.
 *
 * `ARCHIVED` is the one exit that is not a decision: it is what happens when
 * the thing being complained about stops existing.
 */
enum ClaimStatus: string
{
    case PENDING = 'PENDING';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case UPHELD = 'UPHELD';
    case REJECTED = 'REJECTED';
    case ARCHIVED = 'ARCHIVED';

    public function isOpen(): bool
    {
        return self::PENDING === $this || self::UNDER_REVIEW === $this;
    }
}
