<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionAnswer;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionAnswerRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<CorrectionAnswer>
 */
final class DoctrineCorrectionAnswerRepository extends DoctrineRepository implements CorrectionAnswerRepository
{
    public function save(CorrectionAnswer $answer): void
    {
        $this->register($answer);
    }

    public function ofCorrection(CorrectionId $correctionId): array
    {
        return array_values($this->repository()->findBy(
            ['correctionId' => $correctionId->value()],
            ['position' => 'ASC'],
        ));
    }

    protected function entityClass(): string
    {
        return CorrectionAnswer::class;
    }
}
