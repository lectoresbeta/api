<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Infrastructure\Controller;

use LectoresBeta\Work\PublicLink\Application\Command\RevokePublicLink;
use LectoresBeta\Work\PublicLink\Application\Handler\RevokePublicLinkHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/public-links/{publicLinkId}` (`FEAT-WRK-010` `RN-9`).
 *
 * Revocar dos veces devuelve `204` las dos: lo que el autor pedía —que ese
 * enlace no sirva— ya se ha cumplido, y un error aquí sería una pregunta
 * sobre el pasado, no sobre lo que quería.
 */
#[AsController]
final readonly class RevokePublicLinkController
{
    public function __construct(
        private RevokePublicLinkHandler $revoke,
        private Security $security,
    ) {
    }

    public function __invoke(string $publicLinkId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->revoke)(new RevokePublicLink($publicLinkId, $user->getUserIdentifier()));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
