<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\DTO;

use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;

/**
 * Quién puede leer esta obra, y dónde sigue la lista (`FEAT-RDG-010`).
 *
 * **Hace falta tanto como la revocación**: se entra a una obra por tres
 * caminos distintos, así que sin esta lista el autor no sabe a quién le ha
 * dado acceso y no puede elegir a quién retirárselo.
 *
 * Lleva `source` —por dónde entró cada uno— porque es lo que permite
 * responder, meses después, por qué esa persona tiene acceso.
 */
final readonly class BetaReaderPage
{
    /**
     * @param list<array{profile: DirectoryEntry, source: string, earned: bool, grantedAt: \DateTimeImmutable}> $readers
     */
    public function __construct(
        public array $readers,
        public ?string $nextCursor,
    ) {
    }
}
