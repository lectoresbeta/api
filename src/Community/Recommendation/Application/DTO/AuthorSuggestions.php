<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\DTO;

/**
 * Lo que se responde al paso 3 del onboarding (`FEAT-COM-016`).
 *
 * **La decisión de mostrar el paso la toma el servidor** (`RN-10`), y de ahí
 * `shouldDisplay`. Si la tomara el cliente —«si vienen menos de tres, no lo
 * enseño»— habría dos sitios donde cambiarla y uno se quedaría atrás.
 *
 * Ninguna lista corta o vacía es un error: la ausencia de autores es un
 * estado normal de una plataforma recién lanzada, no un fallo.
 */
final readonly class AuthorSuggestions
{
    /**
     * @param list<AuthorSuggestion>                                                         $suggestions
     * @param 'ALREADY_FOLLOWING_SOMEBODY'|'NOT_ENOUGH_AUTHORS'|'ALREADY_FOLLOWING_ALL'|null $reason
     */
    public function __construct(
        public bool $shouldDisplay,
        public ?string $reason,
        public array $suggestions,
    ) {
    }
}
