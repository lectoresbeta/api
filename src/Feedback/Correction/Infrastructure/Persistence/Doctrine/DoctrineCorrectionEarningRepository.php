<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionEarning;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionEarningRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<CorrectionEarning>
 */
final class DoctrineCorrectionEarningRepository extends DoctrineRepository implements CorrectionEarningRepository
{
    public function save(CorrectionEarning $earning): void
    {
        $this->register($earning);
    }

    public function ofCorrection(CorrectionId $correctionId): ?CorrectionEarning
    {
        return $this->repository()->find($correctionId->value());
    }

    public function ofCorrections(array $correctionIds): array
    {
        if ([] === $correctionIds) {
            return [];
        }

        $earnings = [];

        foreach ($this->repository()->findBy([
            'correctionId' => array_map(static fn (CorrectionId $id): string => $id->value(), $correctionIds),
        ]) as $earning) {
            $earnings[$earning->correctionId()->value()] = $earning;
        }

        return $earnings;
    }

    protected function entityClass(): string
    {
        return CorrectionEarning::class;
    }
}
