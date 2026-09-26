<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Entity\NotificationPreference;
use LectoresBeta\User\Preferences\Domain\Entity\UserNotificationSettings;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationChannel;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationTopic;
use LectoresBeta\User\Preferences\Domain\Repository\NotificationPreferenceRepository;

/**
 * @extends DoctrineRepository<NotificationPreference>
 */
final class DoctrineNotificationPreferenceRepository extends DoctrineRepository implements NotificationPreferenceRepository
{
    public function save(NotificationPreference $preference): void
    {
        $this->register($preference);
    }

    public function saveSettings(UserNotificationSettings $settings): void
    {
        $this->entityManager->persist($settings);
    }

    public function settingsOf(UserId $userId): ?UserNotificationSettings
    {
        return $this->entityManager->getRepository(UserNotificationSettings::class)->find($userId->value());
    }

    public function one(UserId $userId, NotificationTopic $topic, NotificationChannel $channel): ?NotificationPreference
    {
        return $this->repository()->find([
            'userId' => $userId->value(),
            'topic' => $topic->value,
            'channel' => $channel->value,
        ]);
    }

    public function ofUser(UserId $userId): array
    {
        return array_values($this->repository()->findBy(['userId' => $userId->value()]));
    }

    protected function entityClass(): string
    {
        return NotificationPreference::class;
    }
}
