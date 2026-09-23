<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Command;

final readonly class AddChapter
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public string $contentHtml,
        public ?string $title = null,
    ) {
    }
}
