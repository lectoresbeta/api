<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Command;

final readonly class RemoveChapter
{
    public function __construct(
        public string $chapterId,
        public string $authorId,
    ) {
    }
}
