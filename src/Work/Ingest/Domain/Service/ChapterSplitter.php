<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Domain\Service;

use LectoresBeta\Work\Ingest\Domain\ValueObject\DocumentBlock;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ExtractedDocument;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ProposedChapter;

/**
 * Dónde **se propone** que acabe cada capítulo (`FEAT-WRK-002`, `W-4`).
 *
 * Es una propuesta y no una decisión: el autor la revisa antes de que se cree
 * nada. Por eso esta clase puede permitirse ser simple — se equivoca sin
 * consecuencias, porque hay alguien mirando.
 *
 * **Corta por los títulos que el autor ya había puesto**, y solo por ellos.
 * En un `.docx` eso es el estilo `Heading` aplicado a un párrafo: información
 * que él escribió, no una heurística sobre líneas en blanco o mayúsculas.
 * Cuando no hay ninguno —un `.txt`, o un documento sin estilos— la propuesta
 * es **un solo capítulo con todo dentro**, que es la respuesta honesta: no se
 * sabe dónde corta, así que no se corta.
 *
 * Un título **sin nada debajo** no abre capítulo. Pasa con una portada o una
 * dedicatoria marcadas como título, y crear un capítulo vacío por cada una
 * dejaría al autor borrando basura antes de empezar.
 *
 * Lo que hay **antes del primer título** tampoco se tira: se queda como
 * primer capítulo. Suele ser un prólogo, y perder texto de alguien porque no
 * encaja en el modelo es el peor error posible aquí.
 */
final class ChapterSplitter
{
    /**
     * @return list<ProposedChapter> siempre al menos uno
     */
    public function split(ExtractedDocument $document): array
    {
        $chapters = [];
        $title = null;
        $body = [];

        foreach ($document->blocks as $block) {
            if (!$block->isHeading) {
                $body[] = $block->text;

                continue;
            }

            // Un título con algo debajo cierra el capítulo anterior; uno
            // seguido de otro título no abre nada, y el segundo gana.
            if ([] !== $body) {
                $chapters[] = new ProposedChapter($title, $body);
                $body = [];
            }

            $title = $block->text;
        }

        if ([] !== $body) {
            $chapters[] = new ProposedChapter($title, $body);
        }

        // Un documento entero sin un solo párrafo bajo ningún título: se
        // devuelve como capítulo único con los títulos dentro, porque el
        // texto es del autor y no se tira nada.
        return [] === $chapters
            ? [new ProposedChapter(null, array_map(
                static fn (DocumentBlock $block): string => $block->text,
                $document->blocks,
            ))]
            : $chapters;
    }
}
