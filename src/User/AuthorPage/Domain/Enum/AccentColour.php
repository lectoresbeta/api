<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Enum;

use LectoresBeta\User\AuthorPage\Domain\Exception\StyleRefused;

/**
 * El color con el que se destacan las cosas en la página de autor
 * (`FEAT-USR-016`).
 *
 * **Un código, nunca un hexadecimal**, y no es cosmético: un color libre
 * acaba interpolado en un atributo `style`, y ahí una cadena que no sea un
 * color es una inyección de CSS por la puerta de atrás. Validar `#rrggbb` con
 * una expresión regular funcionaría hoy y sería el sitio donde algún día se
 * acepte `red;position:fixed`. El enum quita la pregunta.
 */
enum AccentColour: string
{
    case SLATE = 'SLATE';
    case CRIMSON = 'CRIMSON';
    case AMBER = 'AMBER';
    case FOREST = 'FOREST';
    case OCEAN = 'OCEAN';
    case PLUM = 'PLUM';

    public static function orRefuse(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? throw StyleRefused::unknownAccent($value);
    }
}
