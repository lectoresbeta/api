<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\AuditLog\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Moderation\AuditLog\Domain\Entity\AuditEntry;
use LectoresBeta\Moderation\AuditLog\Domain\Repository\AuditEntryRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<AuditEntry>
 */
final class DoctrineAuditEntryRepository extends DoctrineRepository implements AuditEntryRepository
{
    public function add(AuditEntry $entry): void
    {
        $this->register($entry);
    }

    public function search(
        ?PartyId $actorId,
        ?string $action,
        ?string $targetType,
        ?string $targetId,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        int $limit,
        int $offset,
    ): array {
        $query = $this->repository()->createQueryBuilder('e')
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (null !== $actorId) {
            $query->andWhere('e.actorId = :actor')->setParameter('actor', $actorId->value());
        }

        if (null !== $action) {
            $query->andWhere('e.action = :action')->setParameter('action', $action);
        }

        if (null !== $targetType) {
            $query->andWhere('e.targetType = :targetType')->setParameter('targetType', $targetType);
        }

        if (null !== $targetId) {
            $query->andWhere('e.targetId = :targetId')->setParameter('targetId', $targetId);
        }

        if (null !== $from) {
            $query->andWhere('e.occurredAt >= :from')->setParameter('from', $from);
        }

        if (null !== $to) {
            $query->andWhere('e.occurredAt <= :to')->setParameter('to', $to);
        }

        return array_values($query->getQuery()->getResult());
    }

    protected function entityClass(): string
    {
        return AuditEntry::class;
    }
}
