<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Enum\AuthProvider;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;

/**
 * @extends DoctrineRepository<User>
 */
final class DoctrineUserRepository extends DoctrineRepository implements UserRepository
{
    public function save(User $user): void
    {
        $this->register($user);
    }

    public function ofId(UserId $id): ?User
    {
        return $this->repository()->find($id->value());
    }

    public function ofEmail(Email $email): ?User
    {
        return $this->repository()->findOneBy(['email' => $email->value()]);
    }

    public function ofUsername(Username $username): ?User
    {
        return $this->repository()->findOneBy(['username' => $username->value()]);
    }

    public function ofExternalIdentity(AuthProvider $provider, string $externalId): ?User
    {
        return $this->repository()->findOneBy([
            'authProvider' => $provider,
            'externalId' => $externalId,
        ]);
    }

    public function ofIds(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        return array_values($this->repository()->findBy(['id' => $userIds]));
    }

    public function matching(string $query, int $limit): array
    {
        // `ILIKE` y no `LOWER(...) LIKE`: PostgreSQL lo entiende directamente
        // y no hay que normalizar el acento de quien escribe. El comodín va
        // **escapado**: un `%` tecleado por alguien no puede convertir su
        // búsqueda en «devuélvemelo todo».
        $pattern = '%'.addcslashes($query, '%_\\').'%';

        /** @var list<User> $found */
        $found = $this->repository()->createQueryBuilder('u')
            ->where('u.status = :active')
            ->andWhere('LOWER(u.username) LIKE LOWER(:pattern) OR LOWER(u.name) LIKE LOWER(:pattern)')
            ->setParameter('active', AccountStatus::ACTIVE)
            ->setParameter('pattern', $pattern)
            ->orderBy('u.username', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $found;
    }

    public function forAdministration(?string $term, int $limit, int $offset): array
    {
        $query = $this->repository()->createQueryBuilder('u')
            ->orderBy('u.registeredAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if (null !== $term && '' !== trim($term)) {
            // El comodín va **escapado**, igual que en `matching()`: un `%`
            // tecleado por alguien no puede convertir su búsqueda en
            // «devuélvemelo todo».
            $pattern = '%'.addcslashes(trim($term), '%_\\').'%';

            $query
                ->where('LOWER(u.email) LIKE LOWER(:pattern) OR LOWER(u.username) LIKE LOWER(:pattern) OR LOWER(u.name) LIKE LOWER(:pattern)')
                ->setParameter('pattern', $pattern);
        }

        /** @var list<User> $found */
        $found = $query->getQuery()->getResult();

        return $found;
    }

    public function emailIsTaken(Email $email): bool
    {
        return $this->repository()->count(['email' => $email->value()]) > 0;
    }

    public function usernameIsTaken(Username $username): bool
    {
        return $this->repository()->count(['username' => $username->value()]) > 0;
    }

    protected function entityClass(): string
    {
        return User::class;
    }
}
