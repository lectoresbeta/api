<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Application\Command;

final readonly class SetModeratorAlerts
{
    public function __construct(
        public string $userId,
        public bool $enabled,
    ) {
    }
}
