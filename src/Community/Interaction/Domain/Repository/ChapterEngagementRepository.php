<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Repository;

use LectoresBeta\Community\Interaction\Domain\Entity\ChapterEngagement;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;

interface ChapterEngagementRepository
{
    /**
     * Los contadores de un capítulo, o `null` si nadie lo ha tocado todavía.
     *
     * Quien pregunta lee la ausencia como ceros. Sembrar una fila por
     * capítulo solo para poder enseñar dos ceros sería mantener una tabla del
     * tamaño del catálogo para no escribir un `?? 0`.
     */
    public function ofChapter(ChapterId $chapterId): ?ChapterEngagement;

    public function save(ChapterEngagement $engagement): void;
}
