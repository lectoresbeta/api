<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Command;

/**
 * Creating a work (`FEAT-WRK-001`).
 *
 * `authorId` is the authenticated user and **is not taken from the request**:
 * accepting one would be a direct route to creating works in somebody else's
 * name.
 */
final readonly class CreateWork
{
    /**
     * @param list<string> $genres opcionales: una obra sin clasificar existe,
     *                             solo que no aparece cuando alguien filtra
     */
    public function __construct(
        public string $authorId,
        public string $title,
        public ?string $synopsis = null,
        public array $genres = [],
    ) {
    }
}
