<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Enum;

/**
 * Who may see a post (`C-1`, `C-2`): anyone, or the author's followers.
 *
 * The wall **filters by audience on the server** (`RN-7`). A post the reader
 * should not see is never served in the hope that the client will hide it.
 *
 * A repost does not widen it (`RN-8`) and a mention does not grant access to
 * anything (`RN-9`).
 */
enum PostAudience: string
{
    case EVERYONE = 'EVERYONE';
    case FOLLOWERS = 'FOLLOWERS';
}
