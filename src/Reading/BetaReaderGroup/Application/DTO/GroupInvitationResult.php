<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\DTO;

/**
 * Qué pasó al invitar a un grupo entero (`FEAT-RDG-007` `RN-12`).
 *
 * **Es un resultado parcial a propósito.** Devolver `422` porque uno de doce
 * no se puede invitar dejaría al autor sin los once que sí; un resultado
 * descrito es más honesto que un fallo total.
 */
final readonly class GroupInvitationResult
{
    /**
     * @param list<string>        $invited identificadores de las personas invitadas
     * @param list<SkippedMember> $skipped
     */
    public function __construct(
        public string $groupId,
        public array $invited,
        public array $skipped,
    ) {
    }
}
