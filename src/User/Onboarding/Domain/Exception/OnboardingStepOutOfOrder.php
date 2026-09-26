<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The steps are ordered (`FEAT-USR-023` `RN-7`).
 *
 * Not pedantry: the genres step needs somebody's age to exist, because the
 * age gate decides what they may be shown (`FEAT-USR-022`, `OB-7`).
 */
final class OnboardingStepOutOfOrder extends \DomainException implements BusinessFailure
{
    public static function expecting(string $expected): self
    {
        return new self(\sprintf('That step comes later. The onboarding is waiting on: %s.', $expected));
    }

    public function errorCode(): string
    {
        return 'ONBOARDING_STEP_OUT_OF_ORDER';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
