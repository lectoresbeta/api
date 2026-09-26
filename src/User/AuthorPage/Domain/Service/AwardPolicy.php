<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Service;

/**
 * Las cifras de un premio (`FEAT-USR-030`).
 *
 * El mismo sitio y el mismo criterio que `PublishedBookPolicy`: son
 * decisiones con nombre, no constantes técnicas.
 */
final class AwardPolicy
{
    /**
     * `RN-7`. El mismo tope que la bibliografía. Un perfil no es un palmarés
     * interminable, y quien de verdad lo supere tiene una conversación
     * pendiente, no un formulario.
     */
    public const MAX_PER_AUTHOR = 50;

    public const TITLE_MAX_LENGTH = 180;

    public const GRANTOR_MAX_LENGTH = 180;

    /** Una línea para lo que no cabe en el título: «finalista», «categoría relato». */
    public const NOTE_MAX_LENGTH = 280;

    public const URL_MAX_LENGTH = 512;

    /**
     * `RN-5`. El mismo rango que una obra publicada, y por el mismo motivo:
     * atrapar el error de teclado sin discutirle a nadie su trayectoria.
     */
    public static function isCredibleYear(int $year, \DateTimeImmutable $now): bool
    {
        return PublishedBookPolicy::isCredibleYear($year, $now);
    }
}
