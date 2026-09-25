<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Rating\Domain\Repository;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Feedback\Rating\Domain\Entity\WorkRating;

interface WorkRatingRepository
{
    /**
     * La valoración de esta persona sobre esta obra, si ya la puso.
     *
     * Una por lector y obra: valorar otra vez **sustituye**, no acumula. Lo
     * garantiza el índice único; esto ahorra el trabajo.
     */
    public function between(ReaderId $readerId, WorkId $workId): ?WorkRating;

    public function save(WorkRating $rating): void;
}
