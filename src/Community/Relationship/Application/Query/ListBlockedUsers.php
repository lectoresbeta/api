<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Application\Query;

final readonly class ListBlockedUsers
{
    public function __construct(
        public string $userId,
        public ?int $limit,
        public ?string $cursor,
    ) {
    }
}
