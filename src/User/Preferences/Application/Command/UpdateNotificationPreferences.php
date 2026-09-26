<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Command;

final readonly class UpdateNotificationPreferences
{
    /**
     * @param list<array{topic: string, channel: string, enabled: bool}> $choices
     */
    public function __construct(
        public string $userId,
        public ?bool $allMuted,
        public array $choices,
    ) {
    }
}
