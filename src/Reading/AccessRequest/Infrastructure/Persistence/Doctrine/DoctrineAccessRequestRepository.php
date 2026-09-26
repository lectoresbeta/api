<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\QueryBuilder;
use LectoresBeta\Reading\AccessRequest\Domain\Entity\AccessRequest;
use LectoresBeta\Reading\AccessRequest\Domain\Enum\AccessRequestStatus;
use LectoresBeta\Reading\AccessRequest\Domain\Repository\AccessRequestRepository;
use LectoresBeta\Reading\AccessRequest\Domain\ValueObject\AccessRequestId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<AccessRequest>
 */
final class DoctrineAccessRequestRepository extends DoctrineRepository implements AccessRequestRepository
{
    public function save(AccessRequest $request): void
    {
        $this->register($request);
    }

    public function ofId(AccessRequestId $id): ?AccessRequest
    {
        return $this->repository()->find($id->value());
    }

    public function openOf(ReaderId $readerId, WorkId $workId): ?AccessRequest
    {
        return $this->repository()->findOneBy([
            'requesterId' => $readerId->value(),
            'workId' => $workId->value(),
            'status' => AccessRequestStatus::PENDING,
        ]);
    }

    public function ofRequester(ReaderId $readerId, ?AccessRequestStatus $status, ?Cursor $after, int $limit): array
    {
        return $this->page(
            $this->repository()->createQueryBuilder('r')
                ->where('r.requesterId = :requester')
                ->setParameter('requester', $readerId->value()),
            $status,
            $after,
            $limit,
        );
    }

    public function onWork(WorkId $workId, AuthorId $authorId, ?AccessRequestStatus $status, ?Cursor $after, int $limit): array
    {
        return $this->page(
            $this->repository()->createQueryBuilder('r')
                ->where('r.workId = :work')
                ->andWhere('r.authorId = :author')
                ->setParameter('work', $workId->value())
                ->setParameter('author', $authorId->value()),
            $status,
            $after,
            $limit,
        );
    }

    protected function entityClass(): string
    {
        return AccessRequest::class;
    }

    /**
     * El resto de la consulta, que es igual en las dos bandejas.
     *
     * Ordena por **fecha e identificador**, y las dos cosas hacen falta: con
     * la fecha sola, dos solicitudes del mismo instante harían que una fila
     * se repitiese o se perdiera al pasar de página. Es la misma pareja que
     * el cursor transporta, y por eso la comparación es `(fecha, id) <`
     * escrita a mano — Doctrine no tiene comparación de tuplas.
     *
     * @return list<AccessRequest>
     */
    private function page(QueryBuilder $query, ?AccessRequestStatus $status, ?Cursor $after, int $limit): array
    {
        if (null !== $status) {
            $query->andWhere('r.status = :status')->setParameter('status', $status);
        }

        if (null !== $after) {
            $query
                ->andWhere('(r.requestedAt < :at OR (r.requestedAt = :at AND r.id < :id))')
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<AccessRequest> $found */
        $found = $query
            ->orderBy('r.requestedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $found;
    }
}
