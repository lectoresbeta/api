<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Repository;

use LectoresBeta\Work\Manuscript\Domain\Entity\WorkReaderRating;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

interface WorkReaderRatingRepository
{
    public function save(WorkReaderRating $rating): void;

    public function between(WorkId $workId, string $readerId): ?WorkReaderRating;
}
