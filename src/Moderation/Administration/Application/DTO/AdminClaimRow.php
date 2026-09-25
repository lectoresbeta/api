<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Application\DTO;

/**
 * Una reclamación en la ficha de un usuario, **sin su texto**.
 *
 * La descripción es lo que esa persona contó sobre otra, y la ficha de
 * usuario no es el sitio donde se lee: para eso está la reclamación, con sus
 * reglas.
 */
final readonly class AdminClaimRow
{
    public function __construct(
        public string $claimId,
        public string $type,
        public string $status,
        public string $reason,
        public string $submittedAt,
        public bool $filedOnBehalf,
    ) {
    }
}
