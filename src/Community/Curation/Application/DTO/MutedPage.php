<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\DTO;

use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;

/**
 * A quién he silenciado, y dónde sigue la lista (`FEAT-COM-033`).
 *
 * **Sin este listado silenciar sería irreversible en la práctica**, por lo
 * mismo que el de bloqueados: quien silenció a alguien hace tres meses no
 * recuerda su `@usuario`, y además ya no le ve pasar por el muro — que es
 * justamente el efecto.
 */
final readonly class MutedPage
{
    /**
     * @param list<array{profile: DirectoryEntry, mutedAt: \DateTimeImmutable}> $people
     */
    public function __construct(
        public array $people,
        public ?string $nextCursor,
    ) {
    }
}
