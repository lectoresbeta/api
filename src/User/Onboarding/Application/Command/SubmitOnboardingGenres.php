<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\Command;

/**
 * Step 2 (`FEAT-USR-023`).
 */
final readonly class SubmitOnboardingGenres
{
    /**
     * @param list<string> $genreCodes
     */
    public function __construct(
        public string $userId,
        public array $genreCodes,
    ) {
    }
}
