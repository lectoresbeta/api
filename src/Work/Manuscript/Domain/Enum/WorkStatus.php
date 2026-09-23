<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Enum;

/**
 * Where a work stands (`FEAT-WRK-016`, `W-9`).
 *
 * It replaced visibility on the work: a work is always in exactly one of
 * these three. Visibility survives only on the chapter.
 *
 * `VISIBLE` and `IN_CORRECTION` answer different questions. Visible means it
 * can be read; in correction means new feedback is accepted (`RN-7`). The
 * author opens that second door deliberately, because receiving corrections
 * costs credits.
 */
enum WorkStatus: string
{
    case DRAFT = 'DRAFT';
    case VISIBLE = 'VISIBLE';
    case IN_CORRECTION = 'IN_CORRECTION';

    public function isReadableByOthers(): bool
    {
        return self::DRAFT !== $this;
    }

    public function acceptsNewCorrections(): bool
    {
        return self::IN_CORRECTION === $this;
    }
}
