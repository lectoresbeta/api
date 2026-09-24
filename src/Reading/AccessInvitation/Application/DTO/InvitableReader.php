<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\DTO;

/**
 * Alguien a quien el autor todavía puede invitar (`FEAT-RDG-006`).
 *
 * Lo mismo que una tarjeta de perfil y nada más. Quien pregunta está
 * dibujando una fila de un desplegable, no consultando a una persona.
 */
final readonly class InvitableReader
{
    public function __construct(
        public string $userId,
        public string $username,
        public ?string $name,
        public ?string $avatarUrl,
    ) {
    }
}
