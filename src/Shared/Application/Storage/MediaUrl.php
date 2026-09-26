<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Application\Storage;

/**
 * De clave de almacenamiento a dirección pública.
 *
 * Lo que se guarda en la base de datos y lo que viaja entre contextos es
 * **la clave**; la dirección se calcula aquí, en un solo sitio. El día que
 * los ficheros se sirvan desde otro dominio o un CDN cambia esta función, y
 * no una columna de cada fila ni el read model de cada consumidor.
 *
 * Vive en `Application` y no en `Infrastructure` por una razón concreta: la
 * dirección tiene que poder viajar **dentro de un evento de integración** —un
 * consumidor que recibiera una clave tendría que aprender cómo se construye
 * una URL de este sistema, que es justo el acoplamiento que el evento evita—,
 * y los eventos se publican desde Application.
 */
final class MediaUrl
{
    private const PREFIX = '/api/v1/media/';

    public static function of(?string $key): ?string
    {
        return null === $key || '' === $key ? null : self::PREFIX.$key;
    }
}
