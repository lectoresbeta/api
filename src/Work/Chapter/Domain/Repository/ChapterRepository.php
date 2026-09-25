<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Repository;

use LectoresBeta\Work\Chapter\Domain\Entity\Chapter;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

interface ChapterRepository
{
    public function save(Chapter $chapter): void;

    public function ofId(ChapterId $id): ?Chapter;

    /**
     * @return list<Chapter>
     */
    public function ofWork(WorkId $workId): array;

    /**
     * The word count of a whole work, without loading the text of every
     * chapter. A novel runs to tens of thousands of words and this is asked
     * on every edit.
     */
    public function wordCountOfWork(WorkId $workId): int;

    public function countOfWork(WorkId $workId): int;

    /**
     * Cuántos capítulos de esa obra están bloqueados por una reclamación
     * estimada (`FEAT-MOD-003` `RN-9`).
     *
     * Contar y no traerlos: al tercero la obra entera queda bloqueada, y para
     * saberlo no hace falta cargar el texto de ninguno.
     */
    public function countBlockedOfWork(WorkId $workId): int;

    public function remove(Chapter $chapter): void;
}
