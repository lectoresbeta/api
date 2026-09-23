<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\DTO;

/**
 * Where somebody's onboarding stands (`FEAT-USR-022` `RN-5`).
 *
 * It exists so that abandoning the onboarding and coming back lands on the
 * step that is actually pending, instead of starting over.
 *
 * `username` travels with it because the first screen greets the person by it
 * (`RN-8`), and asking for the state and then asking who they are would be
 * two calls for one screen.
 */
final readonly class OnboardingState
{
    public function __construct(
        public string $status,
        public string $username,
        public bool $completed,
    ) {
    }
}
