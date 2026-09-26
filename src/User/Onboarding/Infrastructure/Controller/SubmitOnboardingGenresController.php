<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Onboarding\Application\Command\SubmitOnboardingGenres;
use LectoresBeta\User\Onboarding\Application\Handler\SubmitOnboardingGenresHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `PUT /api/v1/me/onboarding/genres` (`FEAT-USR-023`).
 */
#[AsController]
final readonly class SubmitOnboardingGenresController
{
    public function __construct(
        private SubmitOnboardingGenresHandler $submit,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        ($this->submit)(new SubmitOnboardingGenres(
            CurrentUser::identifierFrom($this->security),
            JsonBody::of($request)->stringList('genres'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
