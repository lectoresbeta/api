<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Repository;

use LectoresBeta\Community\Interaction\Domain\Entity\ChapterLike;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

interface ChapterLikeRepository
{
    public function between(ChapterId $chapterId, MemberId $memberId): ?ChapterLike;

    public function save(ChapterLike $like): void;

    public function remove(ChapterLike $like): void;
}
