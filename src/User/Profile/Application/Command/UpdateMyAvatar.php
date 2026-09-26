<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Command;

/**
 * La foto nueva, en bytes (`FEAT-USR-037`).
 *
 * Bytes y no un fichero subido: `UploadedFile` es de Symfony y no cruza a
 * Application (`AGENTS.md`). Lo que el caso de uso necesita saber de una
 * subida es su contenido, nada más — ni cómo llegó ni cómo se llamaba.
 *
 * `original` es nulo al **reencuadrar**: la imagen sin recortar ya está
 * guardada de la primera vez, y volver a subirla sería mandar por la red algo
 * que no ha cambiado.
 *
 * `crop` se guarda y **nunca se aplica**. El recorte lo hizo el navegador
 * (`RN-4b`); esto solo sirve para que «Editar» reabra el editor donde el
 * usuario lo dejó (`F-10`).
 *
 * @phpstan-type Crop array<string, float|int>
 */
final readonly class UpdateMyAvatar
{
    /**
     * @param array<string, float|int>|null $crop
     */
    public function __construct(
        public string $userId,
        public ?string $image,
        public ?string $original,
        public ?array $crop,
    ) {
    }
}
