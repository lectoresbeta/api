<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Command;

final readonly class SetChapterVisibility
{
    public function __construct(
        public string $chapterId,
        public string $authorId,
        public string $visibility,
    ) {
    }
}
