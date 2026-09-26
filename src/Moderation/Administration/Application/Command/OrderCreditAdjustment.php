<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Application\Command;

final readonly class OrderCreditAdjustment
{
    public function __construct(
        public string $administratorId,
        public string $userId,
        public ?int $amount,
        public ?string $reason,
        public ?string $claimId,
    ) {
    }
}
