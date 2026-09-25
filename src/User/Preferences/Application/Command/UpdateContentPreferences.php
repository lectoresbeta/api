<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Command;

/**
 * @param list<string> $excludedWarnings
 */
final readonly class UpdateContentPreferences
{
    /**
     * @param list<string> $excludedWarnings
     */
    public function __construct(
        public string $userId,
        public array $excludedWarnings,
    ) {
    }
}
