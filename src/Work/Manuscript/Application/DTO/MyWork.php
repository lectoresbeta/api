<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\DTO;

/**
 * Una tarjeta de «Mis relatos» (`FEAT-WRK-015`).
 *
 * **Metadatos, nunca contenido** (`RN-3`): la sinopsis sí, el texto no.
 *
 * `status` aparece aquí y en ningún listado público (`RN-7`): es información
 * de gestión, y saber que una obra ajena está en borrador no le sirve a nadie
 * más que a quien la escribe.
 */
final readonly class MyWork
{
    /**
     * @param list<string> $genres
     */
    public function __construct(
        public string $workId,
        public string $title,
        public ?string $synopsis,
        public string $status,
        public string $accessMode,
        public bool $adultsOnly,
        public int $wordCount,
        public int $chapterCount,
        public array $genres,
        public int $ratingCount,
        /** La media de verdad, sin redondear: `null` mientras nadie la haya valorado. */
        public ?float $ratingAverage,
        public bool $blocked,
        public bool $archived,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
