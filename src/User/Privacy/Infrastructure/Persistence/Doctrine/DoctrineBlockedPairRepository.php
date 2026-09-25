<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Entity\BlockedPair;
use LectoresBeta\User\Privacy\Domain\Repository\BlockedPairRepository;

/**
 * @extends DoctrineRepository<BlockedPair>
 */
final class DoctrineBlockedPairRepository extends DoctrineRepository implements BlockedPairRepository
{
    public function exists(UserId $one, UserId $other): bool
    {
        return null !== $this->between($one, $other);
    }

    public function blockedAmong(UserId $one, array $otherIds): array
    {
        if ([] === $otherIds) {
            return [];
        }

        // Las dos columnas, porque la fila se guarda ordenada y quien
        // bloqueó puede estar en cualquiera de las dos. Mirar solo una
        // escondería la mitad de los bloqueos, que es peor que ninguno:
        // funcionaría casi siempre.
        /** @var list<array{oneId: string, otherId: string}> $pairs */
        $pairs = $this->entityManager->createQueryBuilder()
            ->select('b.oneId AS oneId', 'b.otherId AS otherId')
            ->from(BlockedPair::class, 'b')
            ->where('(b.oneId = :me AND b.otherId IN (:others)) OR (b.otherId = :me AND b.oneId IN (:others))')
            ->setParameter('me', $one->value())
            ->setParameter('others', $otherIds)
            ->getQuery()
            ->getResult();

        $hidden = [];

        foreach ($pairs as $pair) {
            $hidden[] = $pair['oneId'] === $one->value() ? $pair['otherId'] : $pair['oneId'];
        }

        return array_values(array_unique($hidden));
    }

    public function between(UserId $one, UserId $other): ?BlockedPair
    {
        [$first, $second] = BlockedPair::ordered($one->value(), $other->value());

        return $this->repository()->findOneBy(['oneId' => $first, 'otherId' => $second]);
    }

    public function save(BlockedPair $pair): void
    {
        $this->register($pair);
    }

    public function remove(BlockedPair $pair): void
    {
        $this->forget($pair);
    }

    protected function entityClass(): string
    {
        return BlockedPair::class;
    }
}
