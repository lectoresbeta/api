<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Repository;

use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectableChapter;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;

interface CorrectableChapterRepository
{
    public function save(CorrectableChapter $chapter): void;

    public function ofChapter(ChapterId $chapterId): ?CorrectableChapter;
}
