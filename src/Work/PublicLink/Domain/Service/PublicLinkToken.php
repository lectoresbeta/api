<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Domain\Service;

/**
 * El token de un enlace público (`FEAT-WRK-010` `RN-1`, `RN-2`).
 *
 * **Opaco y aleatorio, no derivado**: ni del identificador de la obra, ni del
 * autor, ni de una fecha. Cualquiera de esas tres cosas convertiría la única
 * credencial que protege obra inédita en algo que se puede adivinar sabiendo
 * lo que ya se sabe.
 *
 * El cifrado es `sha256` sin sal y a propósito: la sal defiende contra un
 * diccionario, y aquí no hay diccionario posible —son 256 bits de azar—,
 * mientras que buscar por el cifrado exige que el mismo token dé siempre el
 * mismo resultado.
 */
final class PublicLinkToken
{
    /**
     * 32 bytes en base64url son 43 caracteres. Sobra para que tantearlo no
     * tenga sentido y cabe en una URL que alguien pega en un correo.
     */
    public const BYTES = 32;

    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::BYTES)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
