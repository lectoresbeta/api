<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Query;

final readonly class ListMyClaimThread
{
    public function __construct(
        public string $claimId,
        public string $readerId,
    ) {
    }
}
