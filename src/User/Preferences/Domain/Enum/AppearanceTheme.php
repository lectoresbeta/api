<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Enum;

use LectoresBeta\User\Preferences\Domain\Exception\UnknownTheme;

/**
 * De qué color se ve la aplicación (`FEAT-USR-042`).
 *
 * Tres valores y no dos. `SYSTEM` es el por defecto porque **no decide por
 * nadie**: quien tiene el móvil en oscuro ya dijo lo que quería, y abrirle la
 * aplicación en claro sería contradecirle en la primera pantalla.
 */
enum AppearanceTheme: string
{
    case LIGHT = 'LIGHT';
    case DARK = 'DARK';
    case SYSTEM = 'SYSTEM';

    /**
     * Un valor desconocido **se rechaza nombrándolo** (`RN-3`). Caer al por
     * defecto en silencio dejaría a quien manda `oscuro` creyendo que ha
     * cambiado algo.
     */
    public static function from_(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? throw UnknownTheme::create($value);
    }
}
