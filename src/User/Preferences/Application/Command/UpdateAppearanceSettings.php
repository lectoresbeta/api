<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Command;

final readonly class UpdateAppearanceSettings
{
    public function __construct(
        public string $userId,
        public ?string $theme,
    ) {
    }
}
