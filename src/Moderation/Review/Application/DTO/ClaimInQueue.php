<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\DTO;

/**
 * Una reclamación tal y como la ve el moderador en su cola.
 *
 * Lleva **el objeto reclamado por referencia**, no su contenido: el texto de
 * la obra o de la corrección se lee donde vive, con las comprobaciones de su
 * propio contexto, y no viaja en una lista que puede acabar en una caché o en
 * un log.
 */
final readonly class ClaimInQueue
{
    public function __construct(
        public string $claimId,
        public string $type,
        public string $targetType,
        public string $targetId,
        public string $reason,
        public ?string $description,
        public string $status,
        public string $submittedAt,
    ) {
    }
}
