<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Credits\Pricing\Domain\Entity\CorrectionWindow;
use LectoresBeta\Credits\Pricing\Domain\Repository\CorrectionWindowRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<CorrectionWindow>
 */
final class DoctrineCorrectionWindowRepository extends DoctrineRepository implements CorrectionWindowRepository
{
    public function save(CorrectionWindow $window): void
    {
        $this->register($window);
    }

    public function ofWork(WorkId $workId): ?CorrectionWindow
    {
        return $this->repository()->find($workId->value());
    }

    public function stateOf(array $workIds): array
    {
        if ([] === $workIds) {
            return [];
        }

        /** @var list<CorrectionWindow> $windows */
        $windows = $this->entityManager->createQueryBuilder()
            ->select('w')
            ->from(CorrectionWindow::class, 'w')
            ->where('w.workId IN (:works)')
            ->setParameter('works', $workIds)
            ->getQuery()
            ->getResult();

        $state = [];

        foreach ($windows as $window) {
            // La respuesta la da la entidad y no la consulta: con dos fechas,
            // «abierta» es una comparación, y hacerla en SQL sería la misma
            // regla escrita dos veces.
            $state[$window->workId()->value()] = $window->isOpen();
        }

        return $state;
    }

    protected function entityClass(): string
    {
        return CorrectionWindow::class;
    }
}
