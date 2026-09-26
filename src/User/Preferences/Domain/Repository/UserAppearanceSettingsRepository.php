<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Entity\UserAppearanceSettings;

interface UserAppearanceSettingsRepository
{
    public function ofUser(UserId $userId): ?UserAppearanceSettings;

    public function save(UserAppearanceSettings $settings): void;
}
