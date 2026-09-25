<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Contract;

/**
 * Una obra recomendable, como la pinta el carrusel de la Home
 * (`FEAT-COM-017`).
 *
 * **Metadatos y ni una palabra del texto** (`RN-6`). Que una obra salga aquí
 * no significa que quien la ve pueda abrirla: eso lo decide `FEAT-WRK-004`
 * cuando lo intente.
 *
 * `credits` es la insignia de `FEAT-CRD-013`: **el mínimo** de lo que pagan
 * sus capítulos corregibles, no la media ni el del primero. Una tarjeta que
 * promete más de lo que luego se abona es peor que no enseñar nada. Cero
 * significa que ahora mismo no se puede corregir.
 */
final readonly class RecommendedWork
{
    /**
     * @param list<string> $genres
     * @param list<string> $contentWarnings lo que la obra declara contener.
     *                                      Va en la tarjeta y no solo dentro:
     *                                      una advertencia que solo aparece
     *                                      cuando ya estás leyendo no
     *                                      advierte de nada (`FEAT-WRK-017`
     *                                      `RN-8`)
     */
    public function __construct(
        public string $workId,
        public string $title,
        public ?string $synopsis,
        public string $status,
        public int $chapterCount,
        public int $readingMinutes,
        public bool $adultsOnly,
        public array $contentWarnings,
        public array $genres,
        public int $credits,
        public int $correctionsReceived,
    ) {
    }
}
