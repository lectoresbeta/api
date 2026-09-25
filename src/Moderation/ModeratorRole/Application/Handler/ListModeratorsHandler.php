<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Application\Handler;

use LectoresBeta\Moderation\ModeratorRole\Application\Query\ListModerators;
use LectoresBeta\Moderation\ModeratorRole\Domain\Entity\ModeratorRole;
use LectoresBeta\Moderation\ModeratorRole\Domain\Repository\ModeratorRoleRepository;

/**
 * Quién modera ahora mismo (`FEAT-MOD-004`).
 *
 * Solo los activos. Quien tuvo el rol y lo perdió está en el registro de
 * auditoría, que es donde se mira la historia; esta lista responde a «a quién
 * puedo asignarle algo hoy».
 */
final readonly class ListModeratorsHandler
{
    public function __construct(private ModeratorRoleRepository $roles)
    {
    }

    /**
     * @return list<ModeratorRole>
     */
    public function __invoke(ListModerators $query): array
    {
        return $this->roles->active();
    }
}
