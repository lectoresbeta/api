<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Service;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Application\DTO\PrivacySettingsView;
use LectoresBeta\User\Privacy\Domain\Entity\UserPrivacySettings;
use LectoresBeta\User\Privacy\Domain\Repository\UserPrivacySettingsRepository;

/**
 * Los ajustes de quien pregunta, existan o no todavía.
 *
 * Una cuenta creada antes de que esto existiera no tiene fila. La respuesta
 * **no es «entonces todo vale»**: son los valores por defecto explícitos, que
 * es la misma frase que se habría guardado al crear la cuenta. La diferencia
 * parece retórica y no lo es — una la dice el modelo y la otra la supone
 * quien lee.
 */
final readonly class StoredPrivacySettings
{
    public function __construct(
        private UserPrivacySettingsRepository $settings,
        private Clock $clock,
    ) {
    }

    public function of(UserId $userId): UserPrivacySettings
    {
        return $this->settings->ofUser($userId)
            ?? UserPrivacySettings::defaultsFor($userId, $this->clock->now());
    }

    public static function asView(UserPrivacySettings $settings): PrivacySettingsView
    {
        return new PrivacySettingsView(
            $settings->profileVisibility()->value,
            $settings->commentPermission()->value,
            $settings->messagePermission()->value,
            $settings->updatedAt(),
        );
    }
}
