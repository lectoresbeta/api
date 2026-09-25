<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\DTO;

/**
 * Lo que ve quien abre el enlace: la obra y sus capítulos visibles
 * (`FEAT-WRK-010` `RN-12`, `RN-13`).
 *
 * **Sin el texto.** Una novela entera en una respuesta son megabytes que casi
 * nadie va a leer de una sentada; el texto va capítulo a capítulo, que es
 * además como se lee.
 *
 * `adultsOnly` viaja para que el cliente pueda avisar antes de enseñar nada.
 * Aquí no se puede comprobar la edad de nadie —no hay cuenta—, así que lo
 * único honesto es decirlo y dejar que quien abre decida.
 */
final readonly class PublicWorkPage
{
    /**
     * @param list<PublicChapterSummary> $chapters
     */
    public function __construct(
        public string $workId,
        public string $title,
        public ?string $synopsis,
        public bool $adultsOnly,
        public array $chapters,
    ) {
    }
}
