<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Contract;

/**
 * Los títulos de varios capítulos de una vez.
 *
 * **En lote y no de uno en uno** a propósito: quien lo llama está pintando
 * una página de una bandeja, y una llamada por fila sería un N+1 escondido
 * detrás de un contrato — el mismo motivo por el que `WorkAccessBriefs`
 * responde por lotes.
 */
interface ChapterHeadings
{
    /**
     * @param list<string> $chapterIds
     *
     * @return array<string, ChapterHeading> indexado por identificador; los que no existen no aparecen
     */
    public function ofChapters(array $chapterIds): array;
}
