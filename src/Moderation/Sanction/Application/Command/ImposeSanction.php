<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Application\Command;

final readonly class ImposeSanction
{
    public function __construct(
        public string $moderatorId,
        public string $userId,
        public ?string $type,
        public ?string $reason,
        public ?string $duration,
        public ?string $claimId,
    ) {
    }
}
