<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\User\Profile\Application\Handler\GetMyLiteraryPreferencesHandler;
use LectoresBeta\User\Profile\Application\Query\GetMyLiteraryPreferences;
use LectoresBeta\User\Profile\Infrastructure\Http\GenreListBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/literary-preferences` (`FEAT-USR-009`).
 *
 * **Solo las propias.** Los géneros de otra persona no se consultan por aquí:
 * son parte de su perfil, y qué enseña un perfil lo deciden `FEAT-USR-014` y
 * sus ajustes de privacidad.
 */
#[AsController]
final readonly class GetMyLiteraryPreferencesController
{
    public function __construct(
        private GetMyLiteraryPreferencesHandler $preferences,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(GenreListBody::of(
            ($this->preferences)(new GetMyLiteraryPreferences($user->getUserIdentifier())),
        ));
    }
}
