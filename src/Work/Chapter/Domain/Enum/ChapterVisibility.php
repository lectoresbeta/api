<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Enum;

/**
 * Whether a chapter is shown at all (`W-9`).
 *
 * The last place visibility survives: on the work it was replaced by
 * `WorkStatus`. A hidden chapter is only reachable by its author (`RN-4`).
 */
enum ChapterVisibility: string
{
    case VISIBLE = 'VISIBLE';
    case HIDDEN = 'HIDDEN';
}
