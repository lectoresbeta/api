<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\DTO;

/**
 * One line of the index. No text: the index of a novel is innocuous
 * information and its chapters are not, so they travel apart
 * (`FEAT-WRK-004`).
 */
final readonly class ChapterSummary
{
    public function __construct(
        public string $chapterId,
        public int $position,
        public ?string $title,
        public int $wordCount,
    ) {
    }
}
