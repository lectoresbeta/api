<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Infrastructure\Controller;

use LectoresBeta\Community\Subscription\Application\Handler\GetAuthorSubscriptionHandler;
use LectoresBeta\Community\Subscription\Application\Query\GetAuthorSubscription;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/users/{userId}/subscription` (`FEAT-COM-010`).
 *
 * Lo que el botón del perfil necesita saber, y **solo sobre quien pregunta**.
 * A quién sigue otra persona es `FEAT-COM-027`, y si esas listas son públicas
 * sigue sin decidirse (`CM-14`).
 */
#[AsController]
final readonly class GetAuthorSubscriptionController
{
    public function __construct(
        private GetAuthorSubscriptionHandler $subscription,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        $viewer = $this->security->getUser();

        if (null === $viewer) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $state = ($this->subscription)(new GetAuthorSubscription($viewer->getUserIdentifier(), $userId));

        return new JsonResponse([
            'subscribed' => $state->subscribed,
            'subscribedAt' => $state->subscribedAt?->format(\DATE_ATOM),
        ]);
    }
}
