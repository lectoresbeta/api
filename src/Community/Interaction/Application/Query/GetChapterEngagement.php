<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Query;

final readonly class GetChapterEngagement
{
    public function __construct(
        public string $readerId,
        public string $chapterId,
    ) {
    }
}
