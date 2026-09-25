<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Domain\ValueObject;

use LectoresBeta\Work\Ingest\Domain\Exception\DocumentNotReadable;

/**
 * Lo que se ha podido sacar de un fichero subido (`FEAT-WRK-002`).
 *
 * Un documento **sin un solo párrafo con texto** no es un manuscrito vacío:
 * es un fichero que no se ha sabido leer, y decir «tu obra está vacía»
 * cuando lo que pasa es que el formato no se entiende manda al autor a
 * buscar un problema que no tiene.
 */
final readonly class ExtractedDocument
{
    /**
     * @param list<DocumentBlock> $blocks
     */
    private function __construct(public array $blocks)
    {
    }

    /**
     * @param list<DocumentBlock> $blocks
     */
    public static function of(array $blocks): self
    {
        $clean = array_values(array_filter(
            $blocks,
            static fn (DocumentBlock $block): bool => '' !== trim($block->text),
        ));

        if ([] === $clean) {
            throw DocumentNotReadable::withoutAnyText();
        }

        return new self($clean);
    }
}
