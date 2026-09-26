<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\DTO;

use LectoresBeta\User\Preferences\Domain\Enum\AppearanceTheme;

final readonly class AppearanceSettingsView
{
    public function __construct(
        public AppearanceTheme $theme,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
