<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\DTO;

use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Enum\OnboardingStatus;

/**
 * El contexto de sesión: identidad, estados y dos números.
 *
 * `balance` y `unreadNotifications` son **anulables**, y eso es `RN-7`: si
 * uno de los dos no se puede resolver, el resto se sirve igual. Que no se
 * pueda pintar el saldo no debería impedir navegar.
 *
 * No lleva correo ni fecha de nacimiento (`RN-6`). Es una respuesta que se
 * pide en cada pantalla; cuanto menos viaje, mejor.
 */
final readonly class SessionContext
{
    /**
     * @param list<string> $pendingTours
     */
    public function __construct(
        public string $userId,
        public string $username,
        public ?string $name,
        public ?string $avatarUrl,
        public AccountStatus $accountStatus,
        public OnboardingStatus $onboardingStatus,
        public ?int $balance,
        public ?int $unreadNotifications,
        public array $pendingTours,
    ) {
    }
}
