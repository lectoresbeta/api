<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Onboarding\Domain\Enum\GuidedTour;
use LectoresBeta\User\Onboarding\Domain\Repository\UserTourRepository;
use LectoresBeta\User\Preferences\Application\Service\StoredAppearanceSettings;
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
        private UserTourRepository $tours,
        private StoredAppearanceSettings $appearance,
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
            // Los tours que esta persona no ha visto (`FEAT-USR-026`). Van
            // aquí y no en una petición aparte porque el layout ya pide esta
            // respuesta en cada carga, y una más solo para saber si pintar
            // cuatro globos sería una petición por pantalla.
            $this->pendingTours($user->id()),
            // El tema (`FEAT-USR-042`), por lo mismo que los tours: el layout
            // ya está pidiendo esta respuesta, y una petición más solo para
            // saber de qué color pintar sería una petición por pantalla — y
            // además tardía, que es lo que produce el parpadeo.
            $this->appearance->of($user->id())->theme(),
        );
    }

    /**
     * **Ausencia de fila es «pendiente»**, así que esto no escribe nada.
     * Apuntar en la base de datos que alguien todavía no ha visto un tour, en
     * cada carga de pantalla, sería la escritura más cara y menos útil del
     * producto.
     *
     * @return list<string>
     */
    private function pendingTours(UserId $userId): array
    {
        $pending = [];

        foreach (GuidedTour::cases() as $tour) {
            $seen = $this->tours->of($userId, $tour->value);

            if (null === $seen || !$seen->isFinished()) {
                $pending[] = $tour->value;
            }
        }

        return $pending;
    }

    private function balanceOf(UserId $userId): ?int
    {
        // Nunca visto no es cero: una cuenta recién creada no tiene saldo
        // proyectado todavía, y enseñar un `0` que quizá sea un `10` es peor
        // que decir «ahora mismo no lo sé».
        return $this->balances->ofUser($userId)?->balance();
    }
}
