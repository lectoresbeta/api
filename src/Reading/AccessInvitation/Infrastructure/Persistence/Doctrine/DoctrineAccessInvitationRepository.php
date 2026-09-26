<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\QueryBuilder;
use LectoresBeta\Reading\AccessInvitation\Domain\Entity\AccessInvitation;
use LectoresBeta\Reading\AccessInvitation\Domain\Enum\AccessInvitationStatus;
use LectoresBeta\Reading\AccessInvitation\Domain\Repository\AccessInvitationRepository;
use LectoresBeta\Reading\AccessInvitation\Domain\ValueObject\AccessInvitationId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<AccessInvitation>
 */
final class DoctrineAccessInvitationRepository extends DoctrineRepository implements AccessInvitationRepository
{
    public function save(AccessInvitation $invitation): void
    {
        $this->register($invitation);
    }

    public function ofId(AccessInvitationId $id): ?AccessInvitation
    {
        return $this->repository()->find($id->value());
    }

    public function openFor(ReaderId $inviteeId, WorkId $workId): ?AccessInvitation
    {
        return $this->repository()->findOneBy([
            'inviteeId' => $inviteeId->value(),
            'workId' => $workId->value(),
            'status' => AccessInvitationStatus::PENDING,
        ]);
    }

    public function ofInvitee(ReaderId $inviteeId, ?AccessInvitationStatus $status, ?Cursor $after, int $limit): array
    {
        return $this->page(
            $this->repository()->createQueryBuilder('i')
                ->where('i.inviteeId = :invitee')
                ->setParameter('invitee', $inviteeId->value()),
            $status,
            $after,
            $limit,
        );
    }

    public function onWork(WorkId $workId, ?AccessInvitationStatus $status, ?Cursor $after, int $limit): array
    {
        return $this->page(
            $this->repository()->createQueryBuilder('i')
                ->where('i.workId = :work')
                ->setParameter('work', $workId->value()),
            $status,
            $after,
            $limit,
        );
    }

    protected function entityClass(): string
    {
        return AccessInvitation::class;
    }

    /**
     * El resto de la consulta, igual en las dos bandejas. Ordena por fecha
     * **e identificador**, que es la pareja que el cursor transporta: con la
     * fecha sola, dos invitaciones del mismo instante harían que una fila se
     * repitiese o se perdiera al pasar de página.
     *
     * @return list<AccessInvitation>
     */
    private function page(QueryBuilder $query, ?AccessInvitationStatus $status, ?Cursor $after, int $limit): array
    {
        if (null !== $status) {
            $query->andWhere('i.status = :status')->setParameter('status', $status);
        }

        if (null !== $after) {
            $query
                ->andWhere('(i.createdAt < :at OR (i.createdAt = :at AND i.id < :id))')
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<AccessInvitation> $found */
        $found = $query
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $found;
    }
}
