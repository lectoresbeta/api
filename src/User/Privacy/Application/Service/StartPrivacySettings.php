<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Service;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Entity\UserPrivacySettings;
use LectoresBeta\User\Privacy\Domain\Repository\UserPrivacySettingsRepository;

/**
 * Los ajustes con los que nace una cuenta (`FEAT-USR-038` `RN-4`).
 *
 * Se escriben **en la misma transacción que la cuenta**, no en respuesta a un
 * evento: una cuenta sin ajustes, aunque fuese por un segundo, es una cuenta
 * cuya privacidad alguien tiene que adivinar, y adivinar es exactamente lo
 * que `RN-4` prohíbe.
 *
 * Es una llamada directa entre dos conceptos del mismo contexto, que es lo
 * que la hace legítima. Entre contextos sería un evento.
 */
final readonly class StartPrivacySettings
{
    public function __construct(private UserPrivacySettingsRepository $settings)
    {
    }

    public function forAccount(UserId $userId, \DateTimeImmutable $now): void
    {
        $this->settings->save(UserPrivacySettings::defaultsFor($userId, $now));
    }
}
