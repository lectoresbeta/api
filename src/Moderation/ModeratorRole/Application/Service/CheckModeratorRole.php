<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Application\Service;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\ModeratorRole\Application\Contract\ModeratorRoles;
use LectoresBeta\Moderation\ModeratorRole\Application\Contract\ModeratorStanding;
use LectoresBeta\Moderation\ModeratorRole\Domain\Repository\ModeratorRoleRepository;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * El lado de `Moderation` del contrato.
 *
 * Un rol revocado responde `null` en el acto: eso es lo que hace que `RN-4`
 * —perder el rol cierra el backoffice de inmediato— sea cierto y no una
 * promesa que se cumple cuando caduca un token.
 */
final readonly class CheckModeratorRole implements ModeratorRoles
{
    public function __construct(private ModeratorRoleRepository $roles)
    {
    }

    public function of(string $userId): ?ModeratorStanding
    {
        try {
            $role = $this->roles->ofUser(PartyId::fromString($userId));
        } catch (InvalidValue) {
            return null;
        }

        if (null === $role || !$role->isActive()) {
            return null;
        }

        return new ModeratorStanding($userId, $role->level()->value);
    }
}
