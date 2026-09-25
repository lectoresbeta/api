<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Infrastructure\Controller;

use LectoresBeta\Moderation\ModeratorRole\Application\Command\SetModeratorAlerts;
use LectoresBeta\Moderation\ModeratorRole\Application\Handler\SetModeratorAlertsHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/me/moderator-alerts` (`FEAT-MOD-004` `RN-3`).
 *
 * Lo cambia **el propio moderador**, y por eso cuelga de `/me` y no de
 * `/admin`: no es una decisión de administración sino cómo prefiere trabajar.
 * Hay quien entra a la cola cuando puede en vez de recibir un correo por cada
 * reclamación, y obligarle a renunciar al rol por eso sería convertir una
 * preferencia en una dimisión.
 */
#[AsController]
final readonly class SetModeratorAlertsController
{
    public function __construct(
        private SetModeratorAlertsHandler $setAlerts,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->setAlerts)(new SetModeratorAlerts(
            $user->getUserIdentifier(),
            true === JsonBody::of($request)->bool('enabled'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
