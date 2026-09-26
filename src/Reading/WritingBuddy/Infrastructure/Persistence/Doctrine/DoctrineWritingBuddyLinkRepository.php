<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\WritingBuddy\Domain\Entity\WritingBuddyLink;
use LectoresBeta\Reading\WritingBuddy\Domain\Enum\WritingBuddyStatus;
use LectoresBeta\Reading\WritingBuddy\Domain\Repository\WritingBuddyLinkRepository;
use LectoresBeta\Reading\WritingBuddy\Domain\ValueObject\WritingBuddyLinkId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<WritingBuddyLink>
 */
final class DoctrineWritingBuddyLinkRepository extends DoctrineRepository implements WritingBuddyLinkRepository
{
    /**
     * Los estados que cuentan como vivos: hay propuesta en el aire, o hay
     * vínculo. Los otros dos son historia.
     */
    private const LIVE = [WritingBuddyStatus::PROPOSED, WritingBuddyStatus::ACCEPTED];

    public function ofId(WritingBuddyLinkId $id): ?WritingBuddyLink
    {
        return $this->repository()->find($id->value());
    }

    public function liveBetween(ReaderId $one, ReaderId $other): ?WritingBuddyLink
    {
        // El par se guarda ordenado, así que ordenarlo aquí es lo que hace
        // que `(A,B)` y `(B,A)` encuentren la misma fila.
        $pair = [$one->value(), $other->value()];
        sort($pair);

        return $this->repository()->findOneBy([
            'memberOne' => $pair[0],
            'memberTwo' => $pair[1],
            'status' => self::LIVE,
        ]);
    }

    public function liveOf(ReaderId $reader, int $limit, int $offset): array
    {
        /** @var list<WritingBuddyLink> $found */
        $found = $this->repository()->createQueryBuilder('l')
            ->where('l.memberOne = :me OR l.memberTwo = :me')
            ->andWhere('l.status IN (:live)')
            ->setParameter('me', $reader->value())
            ->setParameter('live', self::LIVE)
            ->orderBy('l.proposedAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $found;
    }

    public function save(WritingBuddyLink $link): void
    {
        $this->register($link);
    }

    protected function entityClass(): string
    {
        return WritingBuddyLink::class;
    }
}
