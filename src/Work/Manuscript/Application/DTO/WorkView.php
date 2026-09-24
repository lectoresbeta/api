<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\DTO;

/**
 * A work as the API shows it: metadata and the **index** of its chapters,
 * never their text (`FEAT-WRK-004`).
 *
 * It carries the author's identifier and nothing else about them. Their
 * email and date of birth belong to `User` and have no business being here
 * (`RN-10`).
 */
final readonly class WorkView
{
    /**
     * @param list<ChapterSummary> $chapters
     * @param list<string>         $genres   códigos del catálogo, no nombres:
     *                                       el nombre lo cura `User` y puede cambiar
     */
    public function __construct(
        public string $workId,
        public string $authorId,
        public string $title,
        public ?string $synopsis,
        public string $status,
        public string $accessMode,
        public bool $adultsOnly,
        public int $wordCount,
        public array $genres,
        public array $chapters,
        public bool $blocked,
    ) {
    }
}
