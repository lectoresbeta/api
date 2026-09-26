<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Domain\Repository;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Entity\BetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupName;

interface BetaReaderGroupRepository
{
    public function save(BetaReaderGroup $group): void;

    public function delete(BetaReaderGroup $group): void;

    public function ofId(BetaReaderGroupId $id): ?BetaReaderGroup;

    /**
     * Las listas de este autor, por nombre. `$query` filtra por nombre sin
     * distinguir mayúsculas (`RN-13`); sin ella salen todas.
     *
     * No pagina: el tope son cincuenta (`RN-4`).
     *
     * @return list<BetaReaderGroup>
     */
    public function ofAuthor(AuthorId $authorId, ?string $query): array;

    public function countOfAuthor(AuthorId $authorId): int;

    /**
     * `RN-3`. `$except` deja fuera al propio grupo, para que renombrarlo con
     * su mismo nombre no choque consigo mismo.
     */
    public function nameIsTaken(AuthorId $authorId, BetaReaderGroupName $name, ?BetaReaderGroupId $except): bool;
}
