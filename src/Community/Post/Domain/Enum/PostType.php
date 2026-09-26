<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Enum;

/**
 * What a post is **for**.
 *
 * Intention, format and audience are three independent dimensions (`RN-5`): a
 * post looking for beta readers can carry any format and any audience.
 * Collapsing them into one enum is the mistake this separation exists to
 * avoid.
 */
enum PostType: string
{
    case GENERAL = 'GENERAL';
    case LOOKING_FOR_BETA_READERS = 'LOOKING_FOR_BETA_READERS';
    case LOOKING_FOR_WRITING_BUDDY = 'LOOKING_FOR_WRITING_BUDDY';
    case OFFERING_AS_BETA_READER = 'OFFERING_AS_BETA_READER';
}
