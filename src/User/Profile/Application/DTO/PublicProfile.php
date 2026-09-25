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
 * Los contadores **no van aquí**: los compone la frontera preguntando a
 * `Community`, `Work` y `Feedback` por sus contratos, igual que en el perfil
 * propio. Un caso de uso que los pidiera tendría que decidir qué hacer cuando
 * uno de los tres no contesta, y esa es una decisión de presentación.
 *
 * La relación **sí**, porque sale del grafo que este contexto ya mantiene
 * para decidir a quién enseña cada perfil. Sin ella el cliente no puede
 * decidir qué botón pintar sin una segunda petición.
 *
 * Es **nula cuando no hay sesión**: no hay relación que contar con un
 * visitante anónimo, y un `false` diría que no le sigue, que es otra cosa.
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
        public ?bool $isFollowing = null,
        public ?bool $isFollowedBy = null,
        public ?bool $isBlocked = null,
    ) {
    }
}
