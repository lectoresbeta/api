<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Service;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Entity\UserReceptionSettings;
use LectoresBeta\User\Preferences\Domain\Repository\UserReceptionSettingsRepository;

/**
 * La fila de ajustes de alguien, creándola si nunca la tocó
 * (`FEAT-USR-011`).
 *
 * Escrito una vez porque lo necesitan leer y guardar, y repetido en los dos
 * sería el segundo el que se olvidara de que una fila ausente significa los
 * valores por defecto y no «todo cerrado».
 */
final readonly class StoredReceptionSettings
{
    public function __construct(
        private UserReceptionSettingsRepository $settings,
        private Clock $clock,
    ) {
    }

    public function of(UserId $userId): UserReceptionSettings
    {
        return $this->settings->ofUser($userId) ?? new UserReceptionSettings($userId, $this->clock->now());
    }
}
