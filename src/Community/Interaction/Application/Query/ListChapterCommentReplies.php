<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Query;

final readonly class ListChapterCommentReplies
{
    public function __construct(
        public string $readerId,
        public string $commentId,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
