<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Entity\NotificationPreference;
use LectoresBeta\User\Preferences\Domain\Entity\UserNotificationSettings;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationChannel;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationTopic;

interface NotificationPreferenceRepository
{
    public function save(NotificationPreference $preference): void;

    public function saveSettings(UserNotificationSettings $settings): void;

    public function settingsOf(UserId $userId): ?UserNotificationSettings;

    public function one(UserId $userId, NotificationTopic $topic, NotificationChannel $channel): ?NotificationPreference;

    /**
     * Todas las decisiones explícitas de alguien.
     *
     * **Solo las explícitas**: lo que no tiene fila toma su valor por
     * defecto, y esa ausencia es lo que hace que un aviso nuevo no exija
     * migración.
     *
     * @return list<NotificationPreference>
     */
    public function ofUser(UserId $userId): array;
}
