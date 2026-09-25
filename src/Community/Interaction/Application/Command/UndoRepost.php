<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Command;

final readonly class UndoRepost
{
    public function __construct(
        public string $postId,
        public string $memberId,
    ) {
    }
}
