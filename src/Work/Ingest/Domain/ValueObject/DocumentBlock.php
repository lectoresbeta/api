<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Domain\ValueObject;

/**
 * Un párrafo del documento, y si venía marcado como título
 * (`FEAT-WRK-002`).
 *
 * **`isHeading` no es una heurística**: en un `.docx` sale del estilo que el
 * autor aplicó (`Heading 1`, `Título 1`…), que es información que él mismo
 * puso. Es la diferencia entre proponer un troceado y adivinarlo.
 *
 * En un `.txt` nunca es cierto, porque ahí no hay nada que leer: un texto
 * plano no distingue un título de una frase corta, y fingir que sí lo
 * distingue sería inventarse capítulos.
 */
final readonly class DocumentBlock
{
    public function __construct(
        public string $text,
        public bool $isHeading,
    ) {
    }
}
