<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\AuditLog\Domain\Repository;

use LectoresBeta\Moderation\AuditLog\Domain\Entity\AuditEntry;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;

/**
 * **Solo inserción y lectura.** No hay actualización ni borrado, y no los
 * habrá: un registro que se puede editar no es un registro (`FEAT-MOD-007`
 * `RN-2`).
 */
interface AuditEntryRepository
{
    public function add(AuditEntry $entry): void;

    /**
     * El registro, de lo más reciente a lo más antiguo.
     *
     * Con filtros porque sin ellos un registro es un montón: quien lo
     * consulta viene con una pregunta concreta —qué hizo esta persona, qué le
     * pasó a este expediente— y recorrer meses de entradas no la responde.
     *
     * @return list<AuditEntry>
     */
    public function search(
        ?PartyId $actorId,
        ?string $action,
        ?string $targetType,
        ?string $targetId,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        int $limit,
        int $offset,
    ): array;
}
