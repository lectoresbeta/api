<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Fewer than three genres (`FEAT-USR-023` `RN-1`, `FEAT-USR-009` `RN-2`).
 *
 * The client disables its own button, and that is a courtesy, not a
 * guarantee: the rule is checked here.
 *
 * It lives in `Profile` and not in `Onboarding` although the onboarding is
 * where it first fires, because what it guards is the literary preferences,
 * and those belong to `Profile` — the onboarding is one of the two doors to
 * them, not their owner.
 */
final class NotEnoughGenres extends \DomainException implements BusinessFailure
{
    public static function atLeast(int $minimum): self
    {
        return new self(\sprintf('Choose at least %d genres.', $minimum));
    }

    public function errorCode(): string
    {
        return 'NOT_ENOUGH_GENRES';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
