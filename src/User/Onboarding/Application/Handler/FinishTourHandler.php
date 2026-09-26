<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Onboarding\Application\Command\FinishTour;
use LectoresBeta\User\Onboarding\Domain\Entity\UserTour;
use LectoresBeta\User\Onboarding\Domain\Enum\GuidedTour;
use LectoresBeta\User\Onboarding\Domain\Exception\UnknownTour;
use LectoresBeta\User\Onboarding\Domain\Repository\UserTourRepository;

/**
 * Dar el tour por visto (`FEAT-USR-026` `RN-1`, `RN-2`).
 *
 * **Cerrarlo cuenta como verlo.** Quien lo cierra en el primer globo ha
 * decidido que no le interesa, y volver a enseñárselo mañana sería no haberle
 * escuchado.
 *
 * Lo que sí se distingue es **cómo** terminó: terminado o cerrado, y en qué
 * paso. Es la única métrica que dice si el tour funciona (`RN-4`), y sin ella
 * «lo vieron entero» y «lo cerraron enseguida» serían el mismo dato.
 *
 * **Se guarda en el servidor y no en el navegador** (`RN-3`). Parece un
 * detalle y no lo es: en local, el tour reaparecería en cada dispositivo y
 * desaparecería al limpiar el navegador. Es la diferencia entre una decisión
 * de producto observable y una preferencia invisible.
 *
 * Es idempotente: repetirlo no reabre nada ni mueve la fecha de terminación.
 */
final readonly class FinishTourHandler
{
    public function __construct(
        private UserTourRepository $tours,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(FinishTour $command): void
    {
        try {
            $tour = GuidedTour::orHome($command->tourId);
        } catch (\InvalidArgumentException) {
            throw UnknownTour::named();
        }

        if (null !== $command->lastStep && ($command->lastStep < 1 || $command->lastStep > $tour->steps())) {
            throw UnknownTour::step($tour->steps());
        }

        $userId = UserId::fromString($command->userId);
        $now = $this->clock->now();
        $seen = $this->tours->of($userId, $tour->value) ?? new UserTour($userId, $tour->value, $now);

        if (null !== $command->lastStep) {
            $seen->advanceTo($command->lastStep, $now);
        }

        if ($command->dismissed) {
            $seen->dismiss($now);
        } else {
            $seen->complete($now);
        }

        $this->session->execute(function () use ($seen): void {
            $this->tours->save($seen);
        });
    }
}
