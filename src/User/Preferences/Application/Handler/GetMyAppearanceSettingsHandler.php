<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Handler;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Application\DTO\AppearanceSettingsView;
use LectoresBeta\User\Preferences\Application\Query\GetMyAppearanceSettings;
use LectoresBeta\User\Preferences\Application\Service\StoredAppearanceSettings;

/**
 * El tema de quien pregunta (`FEAT-USR-042`).
 *
 * Quien nunca lo ha tocado recibe `SYSTEM`, no un hueco: la pantalla tiene
 * que poder pintar el selector el primer día.
 */
final readonly class GetMyAppearanceSettingsHandler
{
    public function __construct(private StoredAppearanceSettings $stored)
    {
    }

    public function __invoke(GetMyAppearanceSettings $query): AppearanceSettingsView
    {
        $settings = $this->stored->of(UserId::fromString($query->userId));

        return new AppearanceSettingsView($settings->theme(), $settings->updatedAt());
    }
}
