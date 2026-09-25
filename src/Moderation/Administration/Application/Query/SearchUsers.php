<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Application\Query;

final readonly class SearchUsers
{
    public function __construct(
        public string $moderatorId,
        public ?string $term,
        public int $limit,
        public int $offset,
    ) {
    }
}
