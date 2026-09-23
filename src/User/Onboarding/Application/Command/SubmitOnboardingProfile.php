<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\Command;

/**
 * Step 1 (`FEAT-USR-022`).
 *
 * `birthDate` arrives as the string the client sent, not as a date. Deciding
 * whether `2000-02-30` is a date is part of the validation this use case
 * owes, and converting it at the boundary would throw that decision away
 * before the rule could be applied.
 */
final readonly class SubmitOnboardingProfile
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $birthDate,
    ) {
    }
}
