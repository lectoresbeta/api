<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\Command;

final readonly class SavePost
{
    public function __construct(
        public string $memberId,
        public string $postId,
    ) {
    }
}
