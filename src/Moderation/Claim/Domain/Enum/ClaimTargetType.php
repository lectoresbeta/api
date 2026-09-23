<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\Enum;

/**
 * What a claim points at.
 *
 * The target is referenced by **type and identifier, never by foreign key**:
 * a claim must not stop another context from deleting what belongs to it.
 *
 * The price is that a target can disappear, and the claim has to stay
 * readable without it. That is accepted, not overlooked.
 */
enum ClaimTargetType: string
{
    case WORK = 'WORK';
    case CHAPTER = 'CHAPTER';
    case CORRECTION = 'CORRECTION';
    case POST = 'POST';
    case POST_COMMENT = 'POST_COMMENT';
    case USER = 'USER';
}
