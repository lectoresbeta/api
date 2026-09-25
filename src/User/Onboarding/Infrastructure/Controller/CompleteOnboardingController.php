<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Infrastructure\Controller;

use LectoresBeta\User\Onboarding\Application\Command\CompleteOnboarding;
use LectoresBeta\User\Onboarding\Application\Handler\CompleteOnboardingHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/me/onboarding/complete` (`FEAT-COM-016`).
 *
 * Lo llama tanto «Siguiente» como «Saltar»: para el servidor son lo mismo, y
 * la diferencia entre haber seguido a alguien o no ya está en las
 * suscripciones que se crearon por el camino.
 *
 * También lo llama el cliente cuando el paso **no se muestra** porque no hay
 * autores que sugerir: el onboarding se cierra igual.
 */
#[AsController]
final readonly class CompleteOnboardingController
{
    public function __construct(
        private CompleteOnboardingHandler $complete,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->complete)(new CompleteOnboarding($user->getUserIdentifier()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
