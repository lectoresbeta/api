<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Enum;

/**
 * The three steps after signing up, and whether they are done.
 *
 * All of them work on an account that is still `PENDING_ACTIVATION`
 * (`FEAT-USR-025` `RN-5`): onboarding is not a write operation in the sense
 * that `decision:0003` blocks.
 */
enum OnboardingStatus: string
{
    case PROFILE_PENDING = 'PROFILE_PENDING';
    case GENRES_PENDING = 'GENRES_PENDING';
    case SUGGESTIONS_PENDING = 'SUGGESTIONS_PENDING';
    case COMPLETED = 'COMPLETED';

    public function isCompleted(): bool
    {
        return self::COMPLETED === $this;
    }

    public function next(): self
    {
        return match ($this) {
            self::PROFILE_PENDING => self::GENRES_PENDING,
            self::GENRES_PENDING => self::SUGGESTIONS_PENDING,
            self::SUGGESTIONS_PENDING, self::COMPLETED => self::COMPLETED,
        };
    }
}
