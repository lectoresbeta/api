<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\DTO;

/**
 * Una tarjeta del catálogo. **Metadatos, nunca contenido**: quién puede leer
 * el texto de una obra es otra decisión, y la resuelve `FEAT-WRK-004`.
 */
final readonly class CatalogueEntry
{
    /**
     * @param list<string> $genres
     * @param list<string> $contentWarnings lo que la obra declara contener.
     *                                      Va en la tarjeta y no solo en la
     *                                      cabecera: una advertencia que solo
     *                                      aparece cuando ya estás leyendo no
     *                                      advierte de nada (`FEAT-WRK-017`
     *                                      `RN-8`)
     */
    public function __construct(
        public string $workId,
        public string $title,
        public ?string $synopsis,
        public string $status,
        public int $wordCount,
        public int $chapterCount,
        public bool $adultsOnly,
        public array $contentWarnings,
        public array $genres,
        public int $correctableChapters,
        /** Lo que gana quien corrija, o cero si ahora mismo no se puede. */
        public int $credits,
        public int $correctionsReceived,
    ) {
    }
}
