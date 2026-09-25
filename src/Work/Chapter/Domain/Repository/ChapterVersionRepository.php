<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Repository;

use LectoresBeta\Work\Chapter\Domain\Entity\ChapterVersion;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;

/**
 * Archivar es copiar, así que este contrato **no ofrece actualización ni
 * borrado**: una copia que se puede editar no sirve para decir qué había
 * entonces.
 */
interface ChapterVersionRepository
{
    public function add(ChapterVersion $version): void;

    public function ofChapterVersion(ChapterId $chapterId, int $version): ?ChapterVersion;
}
