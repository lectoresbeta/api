<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Command;

/**
 * Retirar una obra (`FEAT-WRK-006`).
 *
 * `confirmed` no es una formalidad: un `DELETE` que se dispara por un enlace
 * mal pulsado no debería vaciar un perfil.
 */
final readonly class ArchiveWork
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public bool $confirmed,
    ) {
    }
}
