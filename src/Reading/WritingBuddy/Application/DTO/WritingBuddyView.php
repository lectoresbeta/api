<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Application\DTO;

use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;

/**
 * Una fila de «Writing buddies» (`FEAT-RDG-009`).
 *
 * `mine` dice si la propuesta la hice yo, que es lo que decide si la pantalla
 * enseña «Aceptar / Rechazar» o «Esperando respuesta». Sin él, el cliente
 * tendría que comparar identificadores.
 */
final readonly class WritingBuddyView
{
    public function __construct(
        public string $linkId,
        public ?DirectoryEntry $other,
        public string $status,
        public bool $proposedByMe,
        public \DateTimeImmutable $proposedAt,
    ) {
    }
}
