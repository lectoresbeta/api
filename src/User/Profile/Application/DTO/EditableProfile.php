<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\DTO;

/**
 * Mi perfil, tal y como lo edito (`FEAT-USR-008`).
 *
 * Es el mismo perfil que ven los demás, visto desde dentro, así que tampoco
 * lleva el correo ni la fecha de nacimiento: se cambian por su propio camino
 * (`FEAT-USR-040`) o no se cambian (`S-27`), y mezclarlos aquí haría de una
 * pantalla de presentación una pantalla de seguridad.
 *
 * `username` va de solo lectura. Se enseña junto a los demás campos porque la
 * pantalla lo hace, pero **se cambia por su propio endpoint**: sus reglas
 * —una vez cada 30 días, alias del anterior— no son negociables por compartir
 * formulario.
 */
final readonly class EditableProfile
{
    public function __construct(
        public string $userId,
        public string $username,
        public ?string $name,
        public ?string $description,
        public ?string $avatarUrl,
        public ?string $coverUrl,
    ) {
    }
}
