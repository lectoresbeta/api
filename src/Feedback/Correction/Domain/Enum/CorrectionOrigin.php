<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Enum;

/**
 * Where a correction came from.
 *
 * `PUBLIC_LINK` is the one that matters: those are outside the economy
 * entirely (`FEAT-FBK-008`). They have no reader identity, they cost the
 * author nothing and they pay nobody, and `Credits` does not even consume
 * their event.
 */
enum CorrectionOrigin: string
{
    case BETA_READER = 'BETA_READER';
    case PUBLIC_LINK = 'PUBLIC_LINK';

    public function movesCredits(): bool
    {
        return self::BETA_READER === $this;
    }
}
