<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\Query;

final readonly class ListMutedUsers
{
    public function __construct(
        public string $memberId,
        public ?string $cursor,
        public ?int $limit,
    ) {
    }
}
