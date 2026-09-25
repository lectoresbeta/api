<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Command;

/**
 * El moderador escribe a **una** de las partes (`FEAT-MOD-009`).
 *
 * La ruta del moderador indica a quién escribe; la de la parte no necesita
 * indicarlo, porque solo tiene un hilo: el suyo.
 */
final readonly class WriteToClaimParty
{
    public function __construct(
        public string $claimId,
        public string $moderatorId,
        public ?string $party,
        public ?string $body,
    ) {
    }
}
