<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Application\Command\UpdateAppearanceSettings;
use LectoresBeta\User\Preferences\Application\DTO\AppearanceSettingsView;
use LectoresBeta\User\Preferences\Application\Service\StoredAppearanceSettings;
use LectoresBeta\User\Preferences\Domain\Enum\AppearanceTheme;
use LectoresBeta\User\Preferences\Domain\Repository\UserAppearanceSettingsRepository;

/**
 * Cambiar el tema (`FEAT-USR-042`).
 *
 * `PUT` con un solo campo, así que aquí **el campo es obligatorio**, al revés
 * que en los ajustes de recepción: con dos interruptores, uno ausente
 * significa «no lo toques»; con uno solo, ausente solo puede ser un error de
 * quien llama, y aceptarlo en silencio dejaría creer que se cambió algo.
 */
final readonly class UpdateAppearanceSettingsHandler
{
    public function __construct(
        private StoredAppearanceSettings $stored,
        private UserAppearanceSettingsRepository $settings,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateAppearanceSettings $command): AppearanceSettingsView
    {
        $settings = $this->stored->of(UserId::fromString($command->userId));

        $settings->change(AppearanceTheme::from_($command->theme), $this->clock->now());

        $this->session->execute(function () use ($settings): void {
            $this->settings->save($settings);
        });

        return new AppearanceSettingsView($settings->theme(), $settings->updatedAt());
    }
}
