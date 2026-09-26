<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Enum;

use LectoresBeta\User\AuthorPage\Domain\Exception\StyleRefused;

/**
 * Cómo se ve la página de autor (`FEAT-USR-016`, cierra `U-5`).
 *
 * **Un catálogo cerrado, y nunca CSS.** `U-5` preguntaba «temas cerrados o
 * CSS libre» y anotaba el motivo: riesgo de seguridad si es libre. Conviene
 * dejar escrito por qué, porque «que cada uno escriba su CSS» suena a
 * libertad y es otra cosa — CSS escrito por un usuario y servido a terceros
 * es código ejecutándose en la página de quien mira:
 *
 * - un selector de atributo con una `url()` detrás **exfiltra datos** letra a
 *   letra de lo que hay en la pantalla de otro;
 * - `position` y `z-index` permiten **superponer** un botón falso sobre uno
 *   real;
 * - `@import` y `url()` convierten cada visita en una **petición a un
 *   tercero**, que es como se construye un registro de quién visita a quién.
 *
 * Sanear eso exige un parser con lista blanca de propiedades, y es una
 * defensa frágil: cada versión del navegador trae una propiedad nueva y la
 * lista se queda corta en silencio. **Un enum no tiene ese problema.** Lo que
 * el autor elige es un código; lo que se sirve son estilos que escribió el
 * equipo.
 */
enum AuthorPageTheme: string
{
    case CLASSIC = 'CLASSIC';
    case INK = 'INK';
    case PARCHMENT = 'PARCHMENT';
    case MIDNIGHT = 'MIDNIGHT';
    case LINEN = 'LINEN';
    case BOTANICAL = 'BOTANICAL';

    public static function orRefuse(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? throw StyleRefused::unknownTheme($value);
    }
}
