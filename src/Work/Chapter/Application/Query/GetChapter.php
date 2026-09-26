<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Query;

final readonly class GetChapter
{
    public function __construct(
        public string $chapterId,
        public string $readerId,
    ) {
    }
}
