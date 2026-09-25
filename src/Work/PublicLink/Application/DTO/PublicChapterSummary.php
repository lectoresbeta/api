<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\DTO;

final readonly class PublicChapterSummary
{
    public function __construct(
        public string $chapterId,
        public int $position,
        public ?string $title,
        public int $wordCount,
    ) {
    }
}
