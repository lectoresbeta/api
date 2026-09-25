<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Application\Handler;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\ModeratorRole\Application\Command\SetModeratorAlerts;
use LectoresBeta\Moderation\ModeratorRole\Domain\Exception\ModeratorRoleRefused;
use LectoresBeta\Moderation\ModeratorRole\Domain\Repository\ModeratorRoleRepository;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Apagar o encender el aviso por correo **sin perder el rol** (`FEAT-MOD-004`
 * `RN-3`).
 *
 * Hay quien prefiere entrar a la cola cuando puede en vez de recibir un
 * correo por cada reclamación. Que eso obligara a renunciar al rol sería
 * convertir una preferencia en una dimisión.
 *
 * Lo cambia **el propio moderador** y nadie más: no es una decisión de
 * administración, es cómo prefiere trabajar.
 */
final readonly class SetModeratorAlertsHandler
{
    public function __construct(
        private ModeratorRoleRepository $roles,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(SetModeratorAlerts $command): void
    {
        try {
            $role = $this->roles->ofUser(PartyId::fromString($command->userId));
        } catch (InvalidValue) {
            throw ModeratorRoleRefused::becauseTheAccountIsNotUsable();
        }

        if (null === $role || !$role->isActive()) {
            throw ModeratorRoleRefused::becauseTheAccountIsNotUsable();
        }

        $this->session->execute(function () use ($role, $command): void {
            $role->setEmailAlerts($command->enabled);
            $this->roles->save($role);
        });
    }
}
