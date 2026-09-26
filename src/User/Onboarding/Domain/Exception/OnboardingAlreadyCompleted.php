<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The onboarding runs once (`FEAT-USR-022`).
 *
 * Changing these data later is a different operation with different rules —
 * editing your profile (`FEAT-USR-008`) — and letting the onboarding endpoint
 * do it would mean two ways in with one set of validations.
 */
final class OnboardingAlreadyCompleted extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The onboarding is already finished. Edit your profile instead.');
    }

    public function errorCode(): string
    {
        return 'ONBOARDING_ALREADY_COMPLETED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
