<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Handler;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Application\DTO\AwardView;
use LectoresBeta\User\AuthorPage\Application\Query\ListAwards;
use LectoresBeta\User\AuthorPage\Domain\Repository\AwardRepository;
use LectoresBeta\User\Profile\Application\Handler\GetProfileByUserIdHandler;
use LectoresBeta\User\Profile\Application\Query\GetProfileByUserId;

/**
 * Los méritos de alguien (`FEAT-USR-030` `RN-2`).
 *
 * **Públicos**, como el perfil del que forman parte: acreditar una
 * trayectoria es enseñarla.
 *
 * Se pide el perfil primero, igual que en la bibliografía y por lo mismo: un
 * perfil restringido o eliminado responde `404`, y si esta lista contestara
 * por su cuenta sería un camino lateral para confirmar que una cuenta existe
 * justo cuando su titular ha pedido que no se sepa (`FEAT-USR-038`).
 */
final readonly class ListAwardsHandler
{
    public function __construct(
        private GetProfileByUserIdHandler $profile,
        private AwardRepository $awards,
    ) {
    }

    /**
     * @return list<AwardView>
     */
    public function __invoke(ListAwards $query): array
    {
        $profile = ($this->profile)(new GetProfileByUserId($query->userId, $query->viewerId));

        return array_map(
            AwardView::of(...),
            $this->awards->ofAuthor(UserId::fromString($profile->userId)),
        );
    }
}
