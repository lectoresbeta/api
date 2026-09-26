<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\ModeratorRole\Domain\Entity\ModeratorRole;
use LectoresBeta\Moderation\ModeratorRole\Domain\Repository\ModeratorRoleRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<ModeratorRole>
 */
final class DoctrineModeratorRoleRepository extends DoctrineRepository implements ModeratorRoleRepository
{
    public function save(ModeratorRole $role): void
    {
        $this->register($role);
    }

    public function ofUser(PartyId $userId): ?ModeratorRole
    {
        return $this->repository()->find($userId->value());
    }

    public function active(): array
    {
        return array_values($this->repository()->findBy(['revokedAt' => null], ['grantedAt' => 'ASC']));
    }

    protected function entityClass(): string
    {
        return ModeratorRole::class;
    }
}
