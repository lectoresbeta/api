<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Query;

/**
 * Buscar personas (`FEAT-USR-017`).
 *
 * `term` y `genres` se combinan con **y**: quien manda los dos pide las dos
 * cosas.
 */
final readonly class SearchAuthors
{
    /**
     * @param list<string> $genres
     */
    public function __construct(
        public string $viewerId,
        public ?string $term = null,
        public array $genres = [],
        public int $limit = 20,
    ) {
    }
}
