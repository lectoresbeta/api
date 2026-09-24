<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Command;

final readonly class UpdatePrivacySettings
{
    public function __construct(
        public string $userId,
        public ?string $profileVisibility,
        public ?string $commentPermission,
        public ?string $messagePermission,
    ) {
    }
}
