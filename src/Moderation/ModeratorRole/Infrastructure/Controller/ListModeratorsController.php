<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Infrastructure\Controller;

use LectoresBeta\Moderation\ModeratorRole\Application\Handler\ListModeratorsHandler;
use LectoresBeta\Moderation\ModeratorRole\Application\Query\ListModerators;
use LectoresBeta\Moderation\ModeratorRole\Domain\Entity\ModeratorRole;
use LectoresBeta\User\Account\Application\Contract\ProfileCards;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/admin/moderators` (`FEAT-MOD-004`).
 *
 * Solo los activos: quien tuvo el rol y lo perdió está en el registro de
 * auditoría, que es donde se mira la historia.
 *
 * Usa `ProfileCards` —el contrato sin filtro de privacidad— y no
 * `VisibleProfiles`, por el mismo motivo que la lista de bloqueados: un
 * moderador que cierre su perfil no puede desaparecer de la lista de quien
 * administra, porque entonces no habría forma de quitarle el rol.
 */
#[AsController]
final readonly class ListModeratorsController
{
    public function __construct(
        private ListModeratorsHandler $moderators,
        private ProfileCards $profiles,
    ) {
    }

    public function __invoke(): Response
    {
        $roles = ($this->moderators)(new ListModerators());
        $cards = $this->profiles->of(array_map(
            static fn (ModeratorRole $role): string => $role->userId()->value(),
            $roles,
        ));

        return new JsonResponse([
            'moderators' => array_map(
                static function (ModeratorRole $role) use ($cards): array {
                    $card = $cards[$role->userId()->value()] ?? null;

                    return [
                        'userId' => $role->userId()->value(),
                        'username' => $card?->username,
                        'name' => $card?->name,
                        'level' => $role->level()->value,
                        'emailAlerts' => $role->wantsEmailAlerts(),
                    ];
                },
                $roles,
            ),
        ]);
    }
}
