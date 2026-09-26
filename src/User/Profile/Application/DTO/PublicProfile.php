<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\DTO;

use LectoresBeta\User\AuthorPage\Application\DTO\AuthorLinkView;
use LectoresBeta\User\AuthorPage\Domain\Enum\AccentColour;
use LectoresBeta\User\AuthorPage\Domain\Enum\AuthorPageTheme;

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
    /**
     * @param list<AuthorLinkView> $links las referencias de la página de
     *                                    autor (`FEAT-USR-015`). Van aquí
     *                                    porque **la página de autor es el
     *                                    perfil** (`P-5`): un endpoint aparte
     *                                    habría sido el primer paso hacia dos
     *                                    perfiles que mantener
     */
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
        public array $links = [],
        /**
         * Cómo decidió el autor que se viera su página (`FEAT-USR-016`). Van
         * aquí por lo mismo que las referencias: la página de autor es el
         * perfil, y pedir su decoración aparte sería una petición más para
         * pintar la misma pantalla.
         */
        public AuthorPageTheme $theme = AuthorPageTheme::CLASSIC,
        public AccentColour $accentColour = AccentColour::SLATE,
    ) {
    }
}
