<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Enum\AppearanceTheme;

/**
 * De qué color ve cada quien la aplicación (`FEAT-USR-042`).
 *
 * **Se guarda en el servidor y no en el navegador** por una razón concreta:
 * el tema tiene que sobrevivir al dispositivo. Quien eligió oscuro en el
 * portátil y abre el móvil espera oscuro, y una preferencia en
 * `localStorage` no viaja.
 *
 * Empieza en `SYSTEM`, y **explícitamente**: una fila que falta no puede
 * significar a la vez «no ha decidido» y «que mande el sistema». Sin fila se
 * lee el mismo valor por defecto, dicho en voz alta.
 *
 * No confundir con `FEAT-USR-016`, que es la apariencia de la **página de
 * autor**: aquella la elige el autor y la ven los demás; esta la elige cada
 * quien y solo la ve él.
 */
class UserAppearanceSettings
{
    private string $userId;

    private AppearanceTheme $theme;

    private \DateTimeImmutable $updatedAt;

    public function __construct(UserId $userId, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->theme = AppearanceTheme::SYSTEM;
        $this->updatedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function theme(): AppearanceTheme
    {
        return $this->theme;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function change(AppearanceTheme $theme, \DateTimeImmutable $now): void
    {
        $this->theme = $theme;
        $this->updatedAt = $now;
    }
}
