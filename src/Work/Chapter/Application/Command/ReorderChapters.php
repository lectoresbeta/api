<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Command;

/**
 * El orden entero, no un movimiento (`FEAT-WRK-003`).
 *
 * «Mueve este de la 3 a la 7» obliga al servidor a reconstruir la intención y
 * se rompe cuando dos pestañas hacen lo mismo a la vez. La lista completa es
 * idempotente: aplicarla dos veces deja el mismo orden.
 */
final readonly class ReorderChapters
{
    /**
     * @param list<string> $chapterIds
     */
    public function __construct(
        public string $workId,
        public string $authorId,
        public array $chapterIds,
    ) {
    }
}
