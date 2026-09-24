<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Profile\Application\Command\ChangeMyUsername;
use LectoresBeta\User\Profile\Application\Handler\ChangeMyUsernameHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/me/username` (`FEAT-USR-034`).
 *
 * Tiene endpoint propio aunque la pantalla lo enseñe junto al nombre y la
 * biografía. Un solo «Guardar» en la interfaz no obliga a una sola llamada, y
 * aquí conviene que sean dos: este cambio bloquea 30 días y reserva el nombre
 * anterior, así que un fallo suyo no debe llevarse por delante una biografía
 * que sí se podía guardar.
 *
 * Devuelve `changeableOn` siempre, incluso cuando no ha habido cambio, para
 * que el formulario pueda desactivarse **sin fallar primero**.
 */
#[AsController]
final readonly class ChangeUsernameController
{
    public function __construct(
        private ChangeMyUsernameHandler $change,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $change = ($this->change)(new ChangeMyUsername(
            $user->getUserIdentifier(),
            JsonBody::of($request)->string('username'),
        ));

        return new JsonResponse([
            'username' => $change->username,
            'previousUsername' => $change->previousUsername,
            'aliasExpiresAt' => $change->aliasExpiresAt?->format(\DATE_ATOM),
            'changeableOn' => $change->changeableOn->format(\DATE_ATOM),
        ]);
    }
}
