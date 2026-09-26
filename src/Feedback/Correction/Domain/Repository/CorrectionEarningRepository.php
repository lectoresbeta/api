<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Repository;

use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionEarning;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;

interface CorrectionEarningRepository
{
    public function save(CorrectionEarning $earning): void;

    public function ofCorrection(CorrectionId $correctionId): ?CorrectionEarning;

    /**
     * @param list<CorrectionId> $correctionIds
     *
     * @return array<string, CorrectionEarning> indexado por identificador de corrección
     */
    public function ofCorrections(array $correctionIds): array;
}
