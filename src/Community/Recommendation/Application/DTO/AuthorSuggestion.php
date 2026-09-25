<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\DTO;

/**
 * Un autor que se propone seguir (`FEAT-COM-016`).
 *
 * `matchedGenres` dice **por qué** se sugiere. Viene vacío cuando la
 * sugerencia sale del segundo tramo de la cadena de relleno —los más seguidos
 * de la plataforma, sin filtrar por género— para que la interfaz pueda
 * matizar el texto en vez de prometer una afinidad que no hay.
 */
final readonly class AuthorSuggestion
{
    /**
     * @param list<string> $matchedGenres
     */
    public function __construct(
        public string $userId,
        public ?string $displayName,
        public ?string $avatarUrl,
        public int $followerCount,
        public int $publicationCount,
        public array $matchedGenres,
        public bool $following,
    ) {
    }
}
