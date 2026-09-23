<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Fewer than three genres (`FEAT-USR-023` `RN-1`).
 *
 * The client disables its own button, and that is a courtesy, not a
 * guarantee: the rule is checked here.
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
