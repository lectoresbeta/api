<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Domain\Repository;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\ModeratorRole\Domain\Entity\ModeratorRole;

interface ModeratorRoleRepository
{
    public function save(ModeratorRole $role): void;

    /**
     * La fila de esa cuenta, **viva o revocada**.
     *
     * Revocada también, porque conceder el rol otra vez a quien lo tuvo es
     * reactivar su fila y no crear una segunda: la clave es la cuenta.
     */
    public function ofUser(PartyId $userId): ?ModeratorRole;

    /**
     * @return list<ModeratorRole>
     */
    public function active(): array;
}
