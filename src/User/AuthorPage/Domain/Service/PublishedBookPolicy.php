<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Service;

/**
 * Las cifras de una obra publicada (`FEAT-USR-029`).
 *
 * Juntas y con nombre porque son decisiones, no constantes técnicas: cuántas
 * caben, qué años son creíbles y cuánto ocupa una portada.
 */
final class PublishedBookPolicy
{
    /**
     * `P-16`. Un perfil no es un catálogo: cincuenta obras publicadas están
     * muy por encima de lo que acredita a nadie, y el tope existe para que la
     * bibliografía no se convierta en un tablón de anuncios.
     *
     * Quien de verdad las supere tiene un problema que merece una
     * conversación, no un formulario.
     */
    public const MAX_PER_AUTHOR = 50;

    public const TITLE_MAX_LENGTH = 255;

    public const PUBLISHER_MAX_LENGTH = 255;

    /**
     * El año más antiguo que se admite. No es una fecha arbitraria: por
     * debajo de la imprenta, «publicado» significa otra cosa.
     *
     * El tope existe para atrapar el error de teclado —un `19` o un `202`—,
     * no para discutirle a nadie su bibliografía.
     */
    public const EARLIEST_YEAR = 1450;

    /**
     * Hasta el año que viene: un libro se anuncia antes de salir, y rechazar
     * esa fecha obligaría a mentir o a esperar.
     */
    public const YEARS_AHEAD = 1;

    /**
     * `P-18`. El mismo límite de subida que la foto de perfil: el servidor lo
     * aplica siempre, y anunciarlo en la pantalla es una cortesía.
     */
    public const COVER_MAX_BYTES = 2 * 1024 * 1024;

    /**
     * `P-18`. La portada se guarda con el lado mayor en 900 px, que a
     * proporción de libro (2:3) son 600 × 900: suficiente para la ficha en
     * una pantalla de densidad doble, y nada para almacenar.
     *
     * **No se recorta a 2:3.** La proporción de portada es una recomendación
     * para quien diseña la pantalla, no una regla que se le pueda imponer a
     * un libro: las portadas reales no miden todas lo mismo, y recortar la de
     * alguien para que encaje en una cuadrícula es estropearla. Lo que llega
     * se reescribe entero —sin metadatos— y se devuelve con sus dimensiones,
     * que es lo que el cliente necesita para encajarla sin deformarla.
     */
    public const COVER_MAX_SIDE = 900;

    public static function isCredibleYear(int $year, \DateTimeImmutable $now): bool
    {
        return $year >= self::EARLIEST_YEAR
            && $year <= (int) $now->format('Y') + self::YEARS_AHEAD;
    }
}
