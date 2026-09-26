<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\Handler;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Onboarding\Application\DTO\TourState;
use LectoresBeta\User\Onboarding\Application\Query\GetTourState;
use LectoresBeta\User\Onboarding\Domain\Enum\GuidedTour;
use LectoresBeta\User\Onboarding\Domain\Exception\UnknownTour;
use LectoresBeta\User\Onboarding\Domain\Repository\UserTourRepository;

/**
 * Si hay que enseñar el tour (`FEAT-USR-026`).
 *
 * **No escribe nada.** La ausencia de fila significa «pendiente», así que
 * cargar la Home no crea ninguna: apuntar en la base de datos que alguien
 * todavía no ha hecho nada, en cada visita, sería la escritura más cara y
 * menos útil del producto.
 */
final readonly class GetTourStateHandler
{
    public function __construct(private UserTourRepository $tours)
    {
    }

    public function __invoke(GetTourState $query): TourState
    {
        try {
            $tour = GuidedTour::orHome($query->tourId);
        } catch (\InvalidArgumentException) {
            throw UnknownTour::named();
        }

        $seen = $this->tours->of(UserId::fromString($query->userId), $tour->value);

        return new TourState($tour->value, null === $seen || !$seen->isFinished(), $seen?->lastStep());
    }
}
