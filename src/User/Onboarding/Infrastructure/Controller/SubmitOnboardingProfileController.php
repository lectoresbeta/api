<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Onboarding\Application\Command\SubmitOnboardingProfile;
use LectoresBeta\User\Onboarding\Application\Handler\SubmitOnboardingProfileHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `PUT /api/v1/me/onboarding/profile` (`FEAT-USR-022`).
 *
 * `PUT` y no `POST`: el paso es idempotente, reenviar los mismos datos no
 * cambia el resultado.
 */
#[AsController]
final readonly class SubmitOnboardingProfileController
{
    public function __construct(
        private SubmitOnboardingProfileHandler $submit,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $body = JsonBody::of($request);

        ($this->submit)(new SubmitOnboardingProfile(
            CurrentUser::identifierFrom($this->security),
            (string) $body->string('name'),
            (string) $body->string('birthDate'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
