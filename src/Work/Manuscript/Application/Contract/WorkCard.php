<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Contract;

/**
 * Una obra, como se pinta dentro de la publicación que la cita
 * (`FEAT-COM-028`).
 *
 * **Son metadatos y ninguna palabra del texto.** Lo que esto responde es qué
 * poner en una tarjeta; quién puede leer la obra es otra decisión, y la toma
 * `Work` en su propio endpoint.
 *
 * Es hermano de `WorkAccessBrief`, y deliberadamente no el mismo: aquel
 * responde a quien decide si alguien entra —lleva la modalidad de acceso, que
 * es la respuesta a esa pregunta— y este a quien pinta un muro. Reutilizarlo
 * habría metido `accessMode` en una tarjeta pública, es decir, habría contado
 * a todo el que pase por el muro cómo tiene el autor cerrada su obra.
 *
 * `readingMinutes` viaja calculado y no en palabras. La fórmula es de `Work`
 * (`FEAT-WRK-013`), y mandar el dato crudo obligaría a `Community` a
 * aprendérsela para poder pintar lo mismo.
 */
final readonly class WorkCard
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
        public string $authorId,
        public string $title,
        public ?string $synopsis,
        public string $status,
        public int $chapterCount,
        public int $readingMinutes,
        public bool $adultsOnly,
        public array $contentWarnings,
        public array $genres,
    ) {
    }
}
