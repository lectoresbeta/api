<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Credits\Pricing\Domain\Entity\WorkQuestionnaireDemand;
use LectoresBeta\Credits\Pricing\Domain\Repository\WorkQuestionnaireDemandRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<WorkQuestionnaireDemand>
 */
final class DoctrineWorkQuestionnaireDemandRepository extends DoctrineRepository implements WorkQuestionnaireDemandRepository
{
    public function save(WorkQuestionnaireDemand $demand): void
    {
        $this->register($demand);
    }

    public function ofWork(WorkId $workId): ?WorkQuestionnaireDemand
    {
        return $this->repository()->find($workId->value());
    }

    protected function entityClass(): string
    {
        return WorkQuestionnaireDemand::class;
    }
}
