<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Entity\UserAppearanceSettings;
use LectoresBeta\User\Preferences\Domain\Repository\UserAppearanceSettingsRepository;

/**
 * @extends DoctrineRepository<UserAppearanceSettings>
 */
final class DoctrineUserAppearanceSettingsRepository extends DoctrineRepository implements UserAppearanceSettingsRepository
{
    public function ofUser(UserId $userId): ?UserAppearanceSettings
    {
        return $this->repository()->find($userId->value());
    }

    public function save(UserAppearanceSettings $settings): void
    {
        $this->register($settings);
    }

    protected function entityClass(): string
    {
        return UserAppearanceSettings::class;
    }
}
