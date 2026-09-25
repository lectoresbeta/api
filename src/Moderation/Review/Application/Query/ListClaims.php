<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Query;

final readonly class ListClaims
{
    public function __construct(
        public string $moderatorId,
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
