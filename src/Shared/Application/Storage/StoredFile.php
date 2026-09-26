<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Application\Storage;

/**
 * Un fichero guardado: su contenido y de qué tipo es.
 *
 * No lleva nombre. El del fichero que subió alguien **se descarta** al
 * guardarlo (`FEAT-USR-037` `RN-7`): no se usa como ruta ni se devuelve, y
 * conservarlo aquí sería la forma de que acabase apareciendo en una URL.
 */
final readonly class StoredFile
{
    public function __construct(
        public string $contents,
        public string $contentType,
    ) {
    }
}
