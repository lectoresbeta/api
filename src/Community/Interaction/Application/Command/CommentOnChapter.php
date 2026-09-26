<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Command;

final readonly class CommentOnChapter
{
    public function __construct(
        public string $authorId,
        public string $chapterId,
        public ?string $body,
        public ?string $parentCommentId = null,
    ) {
    }
}
