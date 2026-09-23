<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\DTO;

/**
 * A chapter with its text (`FEAT-WRK-004`).
 *
 * `contentHtml` is served **exactly as stored**. It was sanitised on the way
 * in, which is the whole point of sanitising on write: nothing is kept that
 * we would not serve, so serving needs no second pass.
 */
final readonly class ChapterView
{
    public function __construct(
        public string $chapterId,
        public string $workId,
        public int $position,
        public ?string $title,
        public string $contentHtml,
        public int $wordCount,
    ) {
    }
}
