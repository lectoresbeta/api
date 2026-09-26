<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\ValueObject;

/**
 * Si una cadena es una dirección web que se puede pintar como enlace.
 *
 * Existe porque la regla se aplica **en más de un sitio** —el enlace de
 * compra de una obra publicada (`FEAT-USR-029`) y las referencias de la
 * página de autor (`FEAT-USR-015`)— y es una regla de seguridad: la escribe
 * un usuario y la pulsa cualquiera que abra su perfil. Escrita dos veces, una
 * de las dos copias se queda sin el día que alguien añada un esquema.
 *
 * **Solo `http` y `https`.** Cualquier otro —`javascript:`, `data:`,
 * `file:`— convierte lo que parece un enlace en algo que ejecuta lo que
 * escribió un extraño.
 *
 * Los dos filtros van juntos y ninguno sobra: `parse_url` acepta cosas que no
 * son alcanzables, como `http://`, y `FILTER_VALIDATE_URL` acepta esquemas
 * que aquí no valen. Cada uno atrapa lo que el otro deja pasar.
 *
 * **Nada de esto visita la dirección.** Comprobar que la página existe
 * convertiría cada guardado en una petición saliente hacia donde diga quien
 * la escribe, que es un servidor haciendo de cliente a petición de un
 * desconocido.
 *
 * Un predicado y no un Value Object: lo que varía entre quienes lo usan es el
 * límite de longitud y qué excepción lanzar, que son decisiones suyas.
 */
final class WebAddress
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    public static function isSafe(string $value): bool
    {
        $scheme = parse_url($value, \PHP_URL_SCHEME);

        if (!\is_string($scheme) || !\in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true)) {
            return false;
        }

        return false !== filter_var($value, \FILTER_VALIDATE_URL);
    }
}
