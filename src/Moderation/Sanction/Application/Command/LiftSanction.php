<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Application\Command;

final readonly class LiftSanction
{
    public function __construct(
        public string $sanctionId,
        public string $moderatorId,
        public ?string $reason,
    ) {
    }
}
