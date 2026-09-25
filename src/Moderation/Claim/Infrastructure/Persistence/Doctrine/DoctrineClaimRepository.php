<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\QueryBuilder;
use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimReason;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimStatus;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<Claim>
 */
final class DoctrineClaimRepository extends DoctrineRepository implements ClaimRepository
{
    public function save(Claim $claim): void
    {
        $this->register($claim);
    }

    public function ofId(ClaimId $id): ?Claim
    {
        return $this->repository()->find($id->value());
    }

    public function of(PartyId $reporterId, ClaimTargetType $targetType, string $targetId): ?Claim
    {
        return $this->repository()->findOneBy([
            'reporterId' => $reporterId->value(),
            'targetType' => $targetType,
            'targetId' => $targetId,
        ]);
    }

    public function countBy(PartyId $reporterId, \DateTimeImmutable $since): int
    {
        /** @var int $count */
        $count = $this->repository()->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.reporterId = :reporter')
            ->andWhere('c.submittedAt >= :since')
            ->setParameter('reporter', $reporterId->value())
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    public function by(PartyId $reporterId): array
    {
        return array_values($this->repository()->findBy(
            ['reporterId' => $reporterId->value()],
            ['submittedAt' => 'DESC'],
        ));
    }

    public function about(PartyId $subjectId): array
    {
        return array_values($this->repository()->findBy(
            ['subjectId' => $subjectId->value()],
            ['submittedAt' => 'DESC'],
        ));
    }

    public function openExcludingParty(
        PartyId $moderator,
        int $limit,
        int $offset = 0,
        ?ClaimReason $reason = null,
        ?ClaimTargetType $targetType = null,
    ): array {
        $query = $this->openQueueOf($moderator, $reason, $targetType)
            ->select('c')
            // De la más antigua a la más reciente, y sin forma de cambiarlo
            // (`FEAT-MOD-008` `RN-1`). El identificador desempata porque es
            // un UUIDv7: crece con el tiempo, así que ordena igual que la
            // fecha y hace estable la paginación cuando dos caen en el mismo
            // segundo.
            ->orderBy('c.submittedAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery();

        return array_values($query->getResult());
    }

    public function countOpenExcludingParty(
        PartyId $moderator,
        ?ClaimReason $reason = null,
        ?ClaimTargetType $targetType = null,
    ): int {
        return (int) $this->openQueueOf($moderator, $reason, $targetType)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function lockedById(ClaimId $id): ?Claim
    {
        return $this->repository()->find($id->value(), LockMode::PESSIMISTIC_WRITE);
    }

    protected function entityClass(): string
    {
        return Claim::class;
    }

    /**
     * Las condiciones de la cola, dichas una sola vez.
     *
     * Las comparten el listado y el recuento, y tenían que compartirlas: un
     * total que no cuente lo mismo que la lista es peor que no dar total,
     * porque nadie lo comprueba.
     */
    private function openQueueOf(
        PartyId $moderator,
        ?ClaimReason $reason,
        ?ClaimTargetType $targetType,
    ): QueryBuilder {
        $query = $this->repository()->createQueryBuilder('c')
            ->where('c.status IN (:open)')
            ->andWhere('c.reporterId != :moderator')
            // `subject_id` es nulo cuando todavía no se sabe contra quién va
            // (un capítulo, una publicación). Un `!=` en SQL descartaría esas
            // filas, que son justo las que nadie ha mirado aún.
            ->andWhere('c.subjectId IS NULL OR c.subjectId != :moderator')
            ->setParameter('open', [ClaimStatus::PENDING, ClaimStatus::UNDER_REVIEW])
            ->setParameter('moderator', $moderator->value());

        if (null !== $reason) {
            $query->andWhere('c.reason = :reason')->setParameter('reason', $reason);
        }

        if (null !== $targetType) {
            $query->andWhere('c.targetType = :targetType')->setParameter('targetType', $targetType);
        }

        return $query;
    }
}
