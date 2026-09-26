<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\Command;

final readonly class UnhidePost
{
    public function __construct(
        public string $memberId,
        public string $postId,
    ) {
    }
}
