<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Repository;

use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

interface WorkGenreRepository
{
    /**
     * Sustituye las temáticas de la obra por estas. **No hay «añadir una»**:
     * clasificar es decir qué es una obra, y la operación natural es dejar la
     * lista como el autor la ve en pantalla.
     *
     * @param list<string> $codes
     */
    public function replaceAll(WorkId $workId, array $codes): void;

    /**
     * @return list<string>
     */
    public function codesOf(WorkId $workId): array;
}
