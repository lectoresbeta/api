<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Application\Command;

/**
 * Conceder o revocar el rol (`FEAT-MOD-004` `RN-1`).
 *
 * `level` a `null` **revoca**. Un solo comando para las dos cosas porque es
 * un solo estado: qué es esa cuenta en moderación, y «nada» es una respuesta
 * como las otras dos.
 */
final readonly class SetModeratorRole
{
    public function __construct(
        public string $actorId,
        public string $userId,
        public ?string $level,
        /**
         * El camino de consola (`FEAT-MOD-012`), que es el único por el que
         * este cambio puede llegar **sin un administrador detrás** — porque
         * la primera vez no lo hay.
         *
         * Salta la regla de «nadie se toca su propio rol», que existe para
         * impedir que alguien se ascienda desde la API. Aquí no hay API: hay
         * alguien con acceso al servidor, que ya tiene todo el poder que esa
         * regla protege.
         */
        public bool $fromConsole = false,
    ) {
    }
}
