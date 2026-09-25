<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Application\DTO;

final readonly class AdminSanctionRow
{
    public function __construct(
        public string $sanctionId,
        public string $type,
        public string $reason,
        public string $imposedAt,
        public ?string $expiresAt,
        public ?string $liftedAt,
        public bool $inForce,
    ) {
    }
}
