<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Entity\UserReceptionSettings;

interface UserReceptionSettingsRepository
{
    /**
     * Los ajustes de esta persona, o `null` si nunca los ha tocado.
     *
     * Quien pregunta lee la ausencia como **los valores por defecto**, no
     * como «entonces todo vale»: son la misma frase, dicha en voz alta.
     */
    public function ofUser(UserId $userId): ?UserReceptionSettings;

    public function save(UserReceptionSettings $settings): void;
}
