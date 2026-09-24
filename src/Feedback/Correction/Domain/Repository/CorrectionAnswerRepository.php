<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Repository;

use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionAnswer;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;

interface CorrectionAnswerRepository
{
    public function save(CorrectionAnswer $answer): void;

    /**
     * @return list<CorrectionAnswer>
     */
    public function ofCorrection(CorrectionId $correctionId): array;

    /**
     * The answers of a draft being discarded. There is no case where they
     * outlive their correction: nobody could read them or come back to them.
     */
    public function removeAllOf(CorrectionId $correctionId): void;
}
