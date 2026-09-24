<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\DTO;

/**
 * Un perfil ajeno, visto desde fuera (`FEAT-USR-014`).
 *
 * Lo que importa de este objeto es **lo que no lleva**: ni correo ni fecha de
 * nacimiento, nunca (`RN-1`). Son datos privados y no salen de este contexto
 * ni siquiera hacia su propia API.
 *
 * Tampoco lleva contadores ni el estado de la relación —si le sigo, si me
 * sigue, si hay bloqueo— porque esos datos son de `Community`, que todavía no
 * existe. Devolver ceros habría sido peor que no devolverlos: un contador a
 * cero se lee como «no ha hecho nada», no como «aún no se sabe».
 */
final readonly class PublicProfile
{
    public function __construct(
        public string $userId,
        public string $username,
        public string $canonicalUsername,
        public string $resolvedVia,
        public ?string $name,
        public ?string $description,
        public ?string $avatarUrl,
        public ?string $coverUrl,
    ) {
    }
}
