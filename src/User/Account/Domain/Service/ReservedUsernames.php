<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Service;

use LectoresBeta\User\Account\Domain\ValueObject\Username;

/**
 * Los nombres que nadie puede tener (`FEAT-USR-033` `RN-5`).
 *
 * **No es una lista de palabras prohibidas por las rutas.** Ese problema ya
 * no existe: la URL del perfil lleva `@` delante
 * ([`decision:0005`](../../../../../docs/decisions/0005-username-with-temporary-aliases.md),
 * `FEAT-USR-035`), así que el espacio de nombres de personas y el de secciones
 * de la aplicación no se tocan nunca. Si lo fuera, habría que ampliarla cada
 * vez que se añade una pantalla.
 *
 * Lo que evita es **la suplantación**: que alguien se llame `soporte` y
 * conteste mensajes, o `lectoresbeta` y parezca la plataforma. De ahí que la
 * lista sea corta y esté formada por lo que una persona podría confundir con
 * una voz oficial.
 *
 * Se compara **exacta y en minúsculas**. `administradora` es un nombre
 * legítimo; `admin` no. Una comparación por prefijo secuestraría nombres
 * reales, que es el error contrario y peor.
 *
 * `N-3` sigue abierta: esta lista es un punto de partida razonable, no un
 * catálogo cerrado.
 */
final class ReservedUsernames
{
    private const RESERVED = [
        // La plataforma hablando de sí misma.
        'lectoresbeta', 'lectores_beta', 'oficial', 'official', 'staff', 'equipo',

        // Quien atiende, que es lo que más se suplanta.
        'admin', 'administrador', 'administradores', 'soporte', 'support',
        'ayuda', 'help', 'contacto', 'moderador', 'moderadores', 'moderator',

        // Correo que parece del sistema.
        'noreply', 'no_reply', 'notificaciones', 'notifications', 'sistema', 'system', 'root',

        // Palabras que en una interfaz se leen como un estado y no como
        // alguien: un comentario firmado por «null» o «anonimo» confunde.
        'null', 'undefined', 'anonimo', 'anonymous', 'eliminado', 'deleted',
    ];

    public function isReserved(Username $username): bool
    {
        return \in_array($username->value(), self::RESERVED, true);
    }
}
