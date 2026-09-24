<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\DTO;

/**
 * Una tarjeta del catálogo. **Metadatos, nunca contenido**: quién puede leer
 * el texto de una obra es otra decisión, y la resuelve `FEAT-WRK-004`.
 */
final readonly class CatalogueEntry
{
    public function __construct(
        public string $workId,
        public string $title,
        public ?string $synopsis,
        public string $status,
        public int $wordCount,
        public int $chapterCount,
        public bool $adultsOnly,
        public int $correctableChapters,
        public int $correctionsReceived,
    ) {
    }
}
