<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Handler;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Application\DTO\PrivacySettingsView;
use LectoresBeta\User\Privacy\Application\Query\GetMyPrivacySettings;
use LectoresBeta\User\Privacy\Application\Service\StoredPrivacySettings;

/**
 * Mis ajustes de privacidad (`FEAT-USR-038`).
 *
 * Solo los propios. No hay consulta de los ajenos, y no por descuido: saber
 * que alguien tiene el perfil restringido ya es información sobre esa
 * persona.
 */
final readonly class GetMyPrivacySettingsHandler
{
    public function __construct(private StoredPrivacySettings $settings)
    {
    }

    public function __invoke(GetMyPrivacySettings $query): PrivacySettingsView
    {
        return StoredPrivacySettings::asView($this->settings->of(UserId::fromString($query->userId)));
    }
}
