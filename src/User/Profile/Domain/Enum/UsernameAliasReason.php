<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Enum;

/**
 * Why a username is reserved (`decision:0005`).
 *
 * The difference matters: an alias left behind by a rename still resolves to
 * its owner's profile and can be taken back, while one left behind by a
 * deleted account only blocks the name. Both expire after 30 days.
 */
enum UsernameAliasReason: string
{
    case USERNAME_CHANGED = 'USERNAME_CHANGED';
    case ACCOUNT_DELETED = 'ACCOUNT_DELETED';

    public function resolvesToProfile(): bool
    {
        return self::USERNAME_CHANGED === $this;
    }

    public function canBeReclaimed(): bool
    {
        return self::USERNAME_CHANGED === $this;
    }
}
