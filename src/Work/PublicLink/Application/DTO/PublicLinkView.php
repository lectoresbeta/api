<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\DTO;

/**
 * Un enlace, tal y como lo ve su autor (`FEAT-WRK-010`).
 *
 * **Sin el token** (`RN-2`). Lo que se revoca es el enlace, no la URL, y por
 * eso basta con su identificador.
 */
final readonly class PublicLinkView
{
    public function __construct(
        public string $publicLinkId,
        public ?string $label,
        public int $maxCorrections,
        public string $createdAt,
        public ?string $expiresAt,
        public ?string $revokedAt,
        public bool $usable,
    ) {
    }
}
