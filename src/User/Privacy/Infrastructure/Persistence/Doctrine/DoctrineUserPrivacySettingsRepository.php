<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Entity\UserPrivacySettings;
use LectoresBeta\User\Privacy\Domain\Repository\UserPrivacySettingsRepository;

/**
 * @extends DoctrineRepository<UserPrivacySettings>
 */
final class DoctrineUserPrivacySettingsRepository extends DoctrineRepository implements UserPrivacySettingsRepository
{
    public function save(UserPrivacySettings $settings): void
    {
        $this->register($settings);
    }

    public function ofUser(UserId $userId): ?UserPrivacySettings
    {
        return $this->repository()->find($userId->value());
    }

    public function profileVisibilityOf(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        $visibility = [];

        foreach ($this->repository()->findBy(['userId' => $userIds]) as $settings) {
            $visibility[$settings->userId()->value()] = $settings->profileVisibility();
        }

        return $visibility;
    }

    protected function entityClass(): string
    {
        return UserPrivacySettings::class;
    }
}
