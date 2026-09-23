<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use LectoresBeta\User\Profile\Domain\Entity\UsernameAlias;
use LectoresBeta\User\Profile\Domain\Repository\UsernameAliasRepository;

/**
 * @extends DoctrineRepository<UsernameAlias>
 */
final class DoctrineUsernameAliasRepository extends DoctrineRepository implements UsernameAliasRepository
{
    public function save(UsernameAlias $alias): void
    {
        $this->register($alias);
    }

    public function ofUsername(Username $username): ?UsernameAlias
    {
        return $this->repository()->find($username->value());
    }

    public function isHeldAt(Username $username, \DateTimeImmutable $moment): bool
    {
        $alias = $this->ofUsername($username);

        return null !== $alias && $alias->isInForceAt($moment);
    }

    public function expiredAt(\DateTimeImmutable $moment, int $limit = 500): array
    {
        /** @var list<UsernameAlias> $aliases */
        $aliases = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(UsernameAlias::class, 'a')
            ->where('a.expiresAt <= :moment')
            ->setParameter('moment', $moment)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $aliases;
    }

    public function purge(UsernameAlias $alias): void
    {
        $this->forget($alias);
    }

    protected function entityClass(): string
    {
        return UsernameAlias::class;
    }
}
