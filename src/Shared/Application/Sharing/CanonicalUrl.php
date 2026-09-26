<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Application\Sharing;

/**
 * La dirección pública de algo, compuesta a partir de su plantilla
 * (`FEAT-WRK-011`, `FEAT-COM-020`).
 *
 * La plantilla apunta al **frontend** y no a la API, por lo mismo que las de
 * activación o invitación: lo que se comparte es una página que alguien abre,
 * no un JSON.
 *
 * El identificador se escapa antes de sustituirlo. Hoy siempre es un UUID y
 * no haría falta, pero lo que protege una plantilla de URL no es el dato que
 * se espera sino el que llegue el día que cambie.
 */
final readonly class CanonicalUrl
{
    public static function from(string $template, string $id): string
    {
        return str_replace('{id}', rawurlencode($id), $template);
    }
}
