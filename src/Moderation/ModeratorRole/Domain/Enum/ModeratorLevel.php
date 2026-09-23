<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Domain\Enum;

/**
 * Who can moderate and how far they can go.
 *
 * The first `ADMIN` is created with a console command (`FEAT-MOD-012`,
 * `MOD-6`): there is no bootstrap screen, because a screen that grants
 * administration is a screen somebody will reach.
 */
enum ModeratorLevel: string
{
    case MODERATOR = 'MODERATOR';
    case ADMIN = 'ADMIN';

    public function canGrantRoles(): bool
    {
        return self::ADMIN === $this;
    }

    /** An appeal against a decision is resolved by an admin (`MOD-15`). */
    public function canResolveAppeals(): bool
    {
        return self::ADMIN === $this;
    }
}
