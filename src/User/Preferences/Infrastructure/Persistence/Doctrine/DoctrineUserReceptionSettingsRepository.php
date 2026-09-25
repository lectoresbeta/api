<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Entity\UserReceptionSettings;
use LectoresBeta\User\Preferences\Domain\Repository\UserReceptionSettingsRepository;

/**
 * @extends DoctrineRepository<UserReceptionSettings>
 */
final class DoctrineUserReceptionSettingsRepository extends DoctrineRepository implements UserReceptionSettingsRepository
{
    public function ofUser(UserId $userId): ?UserReceptionSettings
    {
        return $this->repository()->find($userId->value());
    }

    public function save(UserReceptionSettings $settings): void
    {
        $this->register($settings);
    }

    protected function entityClass(): string
    {
        return UserReceptionSettings::class;
    }
}
