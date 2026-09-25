<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Query;

final readonly class ListCommentReplies
{
    public function __construct(
        public string $commentId,
        public string $readerId,
        public ?string $cursor,
        public ?int $limit,
    ) {
    }
}
