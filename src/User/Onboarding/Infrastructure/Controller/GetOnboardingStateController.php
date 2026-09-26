<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Infrastructure\Controller;

use LectoresBeta\User\Onboarding\Application\Handler\GetOnboardingStateHandler;
use LectoresBeta\User\Onboarding\Application\Query\GetOnboardingState;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/me/onboarding` (`FEAT-USR-022`).
 *
 * Lo que hace útil este endpoint es `RN-5`: el onboarding se puede abandonar
 * y retomar, y sin alguien que diga en qué paso se quedó, el cliente no tiene
 * forma de saberlo salvo adivinando.
 */
#[AsController]
final readonly class GetOnboardingStateController
{
    public function __construct(
        private GetOnboardingStateHandler $state,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $state = ($this->state)(new GetOnboardingState(CurrentUser::identifierFrom($this->security)));

        return new JsonResponse([
            'status' => $state->status,
            'username' => $state->username,
            'completed' => $state->completed,
        ]);
    }
}
