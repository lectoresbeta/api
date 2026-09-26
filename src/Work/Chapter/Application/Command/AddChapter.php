<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Command;

/**
 * Añadir un capítulo (`FEAT-WRK-001`, `FEAT-WRK-003`).
 *
 * `position` es opcional y significa **dónde intercalarlo**: sin él va al
 * final, que es lo que hace quien escribe en orden. Una novela se escribe
 * raramente en orden.
 */
final readonly class AddChapter
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public string $contentHtml,
        public ?string $title = null,
        public ?int $position = null,
    ) {
    }
}
