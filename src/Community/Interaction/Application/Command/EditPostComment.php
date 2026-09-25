<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Command;

final readonly class EditPostComment
{
    public function __construct(
        public string $commentId,
        public string $authorId,
        public ?string $body,
    ) {
    }
}
