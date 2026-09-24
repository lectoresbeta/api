<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Query;

/**
 * A quién sigue esta persona (`FEAT-COM-027`).
 *
 * `viewerId` es nulo cuando nadie ha iniciado sesión, y no es un caso raro:
 * un perfil público se comparte, y exigir sesión para abrir sus listas haría
 * inútil compartirlo.
 */
final readonly class ListAuthorSubscriptions
{
    public function __construct(
        public string $userId,
        public ?string $viewerId,
        public ?int $limit,
        public ?string $cursor,
    ) {
    }
}
