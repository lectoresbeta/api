<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Command;

final readonly class LikeChapter
{
    public function __construct(
        public string $memberId,
        public string $chapterId,
    ) {
    }
}
