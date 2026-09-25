<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\User\Profile\Application\DTO\SessionContext;
use LectoresBeta\User\Profile\Application\Handler\GetSessionContextHandler;
use LectoresBeta\User\Profile\Application\Query\GetSessionContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/context` (`FEAT-USR-027`).
 *
 * **Una petición por navegación en vez de cuatro.** Identidad, estado de la
 * cuenta, onboarding, saldo y avisos sin leer son lo que el layout necesita
 * en todas las pantallas, y pedirlos por separado sería pagar cuatro viajes
 * para pintar un menú que no cambia.
 *
 * Funciona con la cuenta **sin activar** (`RN-8`): es de lectura, y es justo
 * entonces cuando hace falta para avisar del estado.
 *
 * `balance` y `unreadNotifications` pueden venir a `null`, y no es un error:
 * significa «ahora mismo no se sabe» (`RN-7`).
 */
#[AsController]
final readonly class GetSessionContextController
{
    public function __construct(
        private GetSessionContextHandler $context,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $context = ($this->context)(new GetSessionContext($user->getUserIdentifier()));

        return new JsonResponse(self::body($context));
    }

    /**
     * @return array<string, mixed>
     */
    private static function body(SessionContext $context): array
    {
        return [
            'userId' => $context->userId,
            'username' => $context->username,
            'name' => $context->name,
            'avatarUrl' => $context->avatarUrl,
            'accountStatus' => $context->accountStatus->value,
            'onboardingStatus' => $context->onboardingStatus->value,
            'credits' => [
                // Un solo número, y puede ser negativo (`FEAT-CRD-018`). La
                // ficha pedía tres —total, disponible y retenido— cuando el
                // sistema retenía créditos; `decision:0006` quitó las
                // retenciones, así que «disponible» y «total» son el mismo.
                'balance' => $context->balance,
            ],
            'unreadNotifications' => $context->unreadNotifications,
            'pendingTours' => $context->pendingTours,
        ];
    }
}
