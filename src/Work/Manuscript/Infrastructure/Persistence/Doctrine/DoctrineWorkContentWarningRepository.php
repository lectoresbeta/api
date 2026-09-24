<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Manuscript\Domain\Entity\WorkContentWarning;
use LectoresBeta\Work\Manuscript\Domain\Enum\ContentWarning;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkContentWarningRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * @extends DoctrineRepository<WorkContentWarning>
 */
final class DoctrineWorkContentWarningRepository extends DoctrineRepository implements WorkContentWarningRepository
{
    public function replaceAll(WorkId $workId, array $warnings): void
    {
        foreach ($this->rowsOf($workId) as $declared) {
            $this->forget($declared);
        }

        foreach ($warnings as $warning) {
            $this->register(new WorkContentWarning($workId, $warning));
        }
    }

    public function of(WorkId $workId): array
    {
        return array_map(
            static fn (WorkContentWarning $row): ContentWarning => $row->warning(),
            $this->rowsOf($workId),
        );
    }

    protected function entityClass(): string
    {
        return WorkContentWarning::class;
    }

    /**
     * @return list<WorkContentWarning>
     */
    private function rowsOf(WorkId $workId): array
    {
        return array_values($this->repository()->findBy(['workId' => $workId->value()], ['warning' => 'ASC']));
    }
}
