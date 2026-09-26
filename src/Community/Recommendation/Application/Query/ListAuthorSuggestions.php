<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Query;

/**
 * A quién proponer seguir, y **desde dónde se pregunta** (`FEAT-COM-016`,
 * `FEAT-COM-018`).
 *
 * La misma pregunta en dos sitios: la pantalla del onboarding y el bloque del
 * muro. Lo que cambia entre ellas no es el criterio —eso sería duplicar la
 * regla— sino cuántas caben y una condición extra: en el muro el bloque
 * **solo aparece a quien no sigue a nadie**, porque existe para resolver
 * justo eso.
 */
final readonly class ListAuthorSuggestions
{
    public function __construct(
        public string $memberId,
        public bool $onlyIfFollowingNobody = false,
        public ?int $howMany = null,
    ) {
    }
}
