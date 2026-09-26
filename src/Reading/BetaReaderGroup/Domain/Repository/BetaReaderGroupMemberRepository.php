<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Domain\Repository;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Entity\BetaReaderGroupMember;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupId;

interface BetaReaderGroupMemberRepository
{
    public function save(BetaReaderGroupMember $member): void;

    public function remove(BetaReaderGroupId $groupId, ReaderId $readerId): void;

    /** Borrar el grupo se lleva a sus miembros (`RN-10`). */
    public function removeAllOf(BetaReaderGroupId $groupId): void;

    public function has(BetaReaderGroupId $groupId, ReaderId $readerId): bool;

    /**
     * Quién está en el grupo, en el orden en que se fueron añadiendo.
     *
     * No pagina: el tope son doscientos (`RN-5`).
     *
     * @return list<BetaReaderGroupMember>
     */
    public function of(BetaReaderGroupId $groupId): array;

    public function countIn(BetaReaderGroupId $groupId): int;

    /**
     * Cuántos miembros tiene cada uno de estos grupos, para pintar la lista
     * sin una consulta por fila.
     *
     * @param list<BetaReaderGroupId> $groupIds
     *
     * @return array<string, int> indexado por identificador de grupo
     */
    public function countsOf(array $groupIds): array;
}
