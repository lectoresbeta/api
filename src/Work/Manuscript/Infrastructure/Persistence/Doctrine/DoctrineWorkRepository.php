<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\QueryBuilder;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * @extends DoctrineRepository<Work>
 */
final class DoctrineWorkRepository extends DoctrineRepository implements WorkRepository
{
    public function save(Work $work): void
    {
        $this->register($work);
    }

    public function ofId(WorkId $id): ?Work
    {
        return $this->repository()->find($id->value());
    }

    public function ofIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $found = [];

        foreach ($this->repository()->findBy(['id' => array_map(
            static fn (WorkId $id): string => $id->value(),
            $ids,
        )]) as $work) {
            $found[$work->id()->value()] = $work;
        }

        return $found;
    }

    public function ofAuthor(AuthorId $authorId, ?WorkStatus $status = null): array
    {
        $criteria = ['authorId' => $authorId->value()];

        if (null !== $status) {
            $criteria['status'] = $status;
        }

        return array_values($this->repository()->findBy($criteria, ['updatedAt' => 'DESC']));
    }

    public function pageOfAuthor(
        AuthorId $authorId,
        ?WorkStatus $status,
        string $sort,
        int $limit,
        int $offset,
    ): array {
        $query = $this->authored($authorId, $status)
            ->select('w')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ('rated' === $sort) {
            $this->bestRatedFirst($query);
        } else {
            $oldestFirst = 'oldest' === $sort;

            $query
                ->orderBy('w.createdAt', $oldestFirst ? 'ASC' : 'DESC')
                // El desempate va en la misma dirección que el criterio. Dos
                // obras creadas en el mismo segundo son lo normal al escribir, y
                // un desempate fijo haría que «más antiguos» devolviera la más
                // nueva primero — un orden que se contradice a sí mismo.
                ->addOrderBy('w.id', $oldestFirst ? 'ASC' : 'DESC');
        }

        return array_values($query->getQuery()->getResult());
    }

    public function countOfAuthor(AuthorId $authorId, ?WorkStatus $status): int
    {
        return (int) $this->authored($authorId, $status)
            ->select('COUNT(w.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByAuthor(AuthorId $authorId): int
    {
        // Las archivadas no cuentan (`FEAT-WRK-006` `RN-10`): para el resto
        // del mundo no existen, y un contador que las incluyera prometería
        // obras que nadie va a poder abrir.
        return (int) $this->repository()->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.authorId = :author')
            ->andWhere('w.archivedAt IS NULL')
            ->setParameter('author', $authorId->value())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function remove(Work $work): void
    {
        $this->forget($work);
    }

    protected function entityClass(): string
    {
        return Work::class;
    }

    /**
     * «Más valorados» (`FEAT-WRK-015`).
     *
     * **Las obras sin valorar van al final, no con las de nota cero.** Una
     * obra que nadie ha valorado no es una obra mal valorada, y mezclarlas
     * castigaría a quien acaba de publicar.
     *
     * La media se calcula en la consulta a partir de la suma y el recuento, y
     * se escala por cien en vez de dividir en coma flotante: aquí solo hace
     * falta para ordenar, y dos decimales separan todo lo que hay que separar
     * en una escala de cinco. A igual media, primero la que tiene más
     * valoraciones — un cinco de una persona no vale lo que un cinco de
     * veinte.
     */
    private function bestRatedFirst(QueryBuilder $query): void
    {
        $query
            ->addSelect('CASE WHEN w.ratingCount = 0 THEN 1 ELSE 0 END AS HIDDEN unrated')
            ->addSelect('CASE WHEN w.ratingCount = 0 THEN 0 ELSE (w.ratingSum * 100 / w.ratingCount) END AS HIDDEN score')
            ->orderBy('unrated', 'ASC')
            ->addOrderBy('score', 'DESC')
            ->addOrderBy('w.ratingCount', 'DESC')
            ->addOrderBy('w.createdAt', 'DESC')
            ->addOrderBy('w.id', 'DESC');
    }

    private function authored(AuthorId $authorId, ?WorkStatus $status): QueryBuilder
    {
        $query = $this->repository()->createQueryBuilder('w')
            ->where('w.authorId = :author')
            ->setParameter('author', $authorId->value());

        if (null !== $status) {
            $query->andWhere('w.status = :status')->setParameter('status', $status);
        }

        return $query;
    }
}
