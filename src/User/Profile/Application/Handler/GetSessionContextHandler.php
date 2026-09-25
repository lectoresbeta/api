<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Profile\Application\DTO\SessionContext;
use LectoresBeta\User\Profile\Application\Port\SessionSideloads;
use LectoresBeta\User\Profile\Application\Query\GetSessionContext;
use LectoresBeta\User\Profile\Application\Service\MyProfile;
use LectoresBeta\User\Profile\Domain\Repository\KnownCreditBalanceRepository;

/**
 * Todo lo que el layout necesita, en una sola petición (`FEAT-USR-027`).
 *
 * **Sin esto, cada navegación dispararía cuatro peticiones para pintar un
 * menú lateral que no cambia.**
 *
 * La identidad y los estados salen del propio contexto. El saldo sale de la
 * copia que `User` mantiene escuchando a `Credits` —que no publica contratos,
 * ni siquiera de consulta— y los avisos sin leer, del contrato publicado de
 * `Notification`, que sí lo hace.
 *
 * Es de **solo lectura** (`RN-4`). Un endpoint que se llama en todas las
 * pantallas sería un sitio pésimo donde esconder un efecto.
 */
final readonly class GetSessionContextHandler
{
    public function __construct(
        private MyProfile $profile,
        private KnownCreditBalanceRepository $balances,
        private SessionSideloads $sideloads,
    ) {
    }

    public function __invoke(GetSessionContext $query): SessionContext
    {
        $user = $this->profile->of($query->userId);

        return new SessionContext(
            $user->id()->value(),
            $user->username()->value(),
            $user->name()?->value(),
            $user->avatarUrl(),
            $user->status(),
            $user->onboardingStatus(),
            $this->balanceOf($user->id()),
            $this->sideloads->unreadNotifications($query->userId),
            // El tour todavía no existe como modelo (`FEAT-USR-026`). La
            // lista vacía es la respuesta correcta —no hay ninguno
            // pendiente— y no una omisión: el cliente ya puede leerla.
            [],
        );
    }

    private function balanceOf(UserId $userId): ?int
    {
        // Nunca visto no es cero: una cuenta recién creada no tiene saldo
        // proyectado todavía, y enseñar un `0` que quizá sea un `10` es peor
        // que decir «ahora mismo no lo sé».
        return $this->balances->ofUser($userId)?->balance();
    }
}
