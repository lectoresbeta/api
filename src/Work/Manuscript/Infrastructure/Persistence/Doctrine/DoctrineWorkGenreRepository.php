<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Manuscript\Domain\Entity\WorkGenre;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkGenreRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * @extends DoctrineRepository<WorkGenre>
 */
final class DoctrineWorkGenreRepository extends DoctrineRepository implements WorkGenreRepository
{
    /**
     * **Solo la diferencia.** No es una optimización: la identidad de una
     * fila es `(work_id, genre_code)`, y borrar y volver a registrar la misma
     * en la misma transacción deja a la unidad de trabajo con dos objetos
     * para el mismo identificador, y falla. Reclasificar conservando una
     * temática —el caso normal— se llevaría un error.
     */
    public function replaceAll(WorkId $workId, array $codes): void
    {
        $wanted = array_values(array_unique(array_map(strtoupper(...), $codes)));
        $current = $this->codesOf($workId);

        foreach ($this->rowsOf($workId) as $genre) {
            if (!\in_array($genre->genreCode(), $wanted, true)) {
                $this->forget($genre);
            }
        }

        foreach (array_diff($wanted, $current) as $code) {
            $this->register(new WorkGenre($workId, $code));
        }
    }

    public function codesOf(WorkId $workId): array
    {
        return array_map(static fn (WorkGenre $genre): string => $genre->genreCode(), $this->rowsOf($workId));
    }

    protected function entityClass(): string
    {
        return WorkGenre::class;
    }

    /**
     * @return list<WorkGenre>
     */
    private function rowsOf(WorkId $workId): array
    {
        return array_values($this->repository()->findBy(['workId' => $workId->value()], ['genreCode' => 'ASC']));
    }
}
