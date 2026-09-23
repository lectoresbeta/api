<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Enum;

/**
 * Who may become a beta reader of this work.
 *
 * A different axis from `WorkStatus`, and only meaningful while the work is
 * `IN_CORRECTION` (`RN-8`). Status answers «can it be corrected at all»; this
 * answers «by whom».
 *
 * The profile-wide privacy setting is a ceiling over this: a work may be
 * stricter, never more open (`FEAT-USR-038` `S-14`). The two are stored apart
 * and evaluated together, so relaxing the profile does not lose what each
 * work had chosen.
 */
enum BetaReaderAccessMode: string
{
    case PUBLIC = 'PUBLIC';
    case ON_REQUEST = 'ON_REQUEST';
    case PRIVATE = 'PRIVATE';

    public function acceptsRequests(): bool
    {
        return self::ON_REQUEST === $this;
    }
}
