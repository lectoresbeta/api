<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Service;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Entity\UserAppearanceSettings;
use LectoresBeta\User\Preferences\Domain\Repository\UserAppearanceSettingsRepository;

/**
 * La fila de apariencia de alguien, creándola si nunca la tocó
 * (`FEAT-USR-042` `RN-2`).
 *
 * El hermano de `StoredReceptionSettings`, y está por lo mismo: leer y
 * guardar lo necesitan, y la segunda copia sería la que se olvidara de que
 * una fila ausente significa `SYSTEM` y no un hueco.
 */
final readonly class StoredAppearanceSettings
{
    public function __construct(
        private UserAppearanceSettingsRepository $settings,
        private Clock $clock,
    ) {
    }

    public function of(UserId $userId): UserAppearanceSettings
    {
        return $this->settings->ofUser($userId) ?? new UserAppearanceSettings($userId, $this->clock->now());
    }
}
