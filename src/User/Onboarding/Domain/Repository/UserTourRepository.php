<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Onboarding\Domain\Entity\UserTour;

interface UserTourRepository
{
    /**
     * Lo que sabemos de ese tour para esa persona, o `null` si nunca lo ha
     * tocado.
     *
     * La ausencia de fila significa «pendiente», y por eso no se crea ninguna
     * al entrar: escribir en la base de datos cada vez que alguien carga la
     * Home para apuntar que no ha hecho nada sería la escritura más cara y
     * menos útil del producto.
     */
    public function of(UserId $userId, string $tourId): ?UserTour;

    public function save(UserTour $tour): void;
}
