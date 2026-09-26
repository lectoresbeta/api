<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Query;

final readonly class ListPostComments
{
    public function __construct(
        public string $postId,
        public string $readerId,
        public ?string $sort,
        public ?string $cursor,
        public ?int $limit,
    ) {
    }
}
