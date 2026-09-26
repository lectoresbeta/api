<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Domain\Enum;

/**
 * Who a privacy setting lets in (`FEAT-USR-038` `RN-4b`).
 *
 * The same three values for the three settings, on purpose: three different
 * scales would be three different things to learn for no gain.
 */
enum PrivacyAudience: string
{
    case EVERYONE = 'EVERYONE';
    case FOLLOWERS = 'FOLLOWERS';
    case NOBODY = 'NOBODY';

    /**
     * The profile setting is a ceiling: a work may be stricter, never more
     * open (`FEAT-USR-038` `RN-2`, `S-14`). Authorisation evaluates both and
     * keeps the narrower one.
     */
    public function isAtLeastAsOpenAs(self $other): bool
    {
        return $this->openness() >= $other->openness();
    }

    public function narrowest(self $other): self
    {
        return $this->openness() <= $other->openness() ? $this : $other;
    }

    private function openness(): int
    {
        return match ($this) {
            self::NOBODY => 0,
            self::FOLLOWERS => 1,
            self::EVERYONE => 2,
        };
    }
}
