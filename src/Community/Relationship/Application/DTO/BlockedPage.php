<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Application\DTO;

use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;

/**
 * A quién tengo bloqueado, y dónde sigue la lista.
 *
 * **Sin este listado el bloqueo sería irreversible en la práctica**: quien
 * bloqueó a alguien hace tres meses no recuerda su `@usuario`, y sin poder
 * encontrarlo no puede deshacerlo.
 */
final readonly class BlockedPage
{
    /**
     * @param list<array{profile: DirectoryEntry, blockedAt: \DateTimeImmutable}> $people
     */
    public function __construct(
        public array $people,
        public ?string $nextCursor,
    ) {
    }
}
