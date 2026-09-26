<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Enum\AccentColour;
use LectoresBeta\User\AuthorPage\Domain\Enum\AuthorPageTheme;

/**
 * Cómo ha decidido el autor que se vea su página (`FEAT-USR-016`).
 *
 * Dos códigos y nada más. **El fondo no está aquí**: es la portada de la
 * cuenta, que ya existía con su `coverUrl` y lo único que le faltaba era el
 * endpoint. Duplicar la imagen habría dejado dos sitios donde vive la misma
 * cosa.
 *
 * Empieza en `CLASSIC` con acento `SLATE`, y **explícitamente**: una fila que
 * falta no puede significar a la vez «no ha decidido» y «el tema de
 * siempre».
 *
 * No confundir con `FEAT-USR-042`, que es el tema de la aplicación: aquel lo
 * elige cada quien y solo lo ve él; este lo elige el autor y lo ven los
 * demás.
 */
class AuthorPageStyle
{
    private string $userId;

    private AuthorPageTheme $theme;

    private AccentColour $accentColour;

    private \DateTimeImmutable $updatedAt;

    public function __construct(UserId $userId, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->theme = AuthorPageTheme::CLASSIC;
        $this->accentColour = AccentColour::SLATE;
        $this->updatedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function theme(): AuthorPageTheme
    {
        return $this->theme;
    }

    public function accentColour(): AccentColour
    {
        return $this->accentColour;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function change(AuthorPageTheme $theme, AccentColour $accentColour, \DateTimeImmutable $now): void
    {
        $this->theme = $theme;
        $this->accentColour = $accentColour;
        $this->updatedAt = $now;
    }
}
