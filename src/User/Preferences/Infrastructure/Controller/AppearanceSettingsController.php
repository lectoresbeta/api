<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Preferences\Application\Command\UpdateAppearanceSettings;
use LectoresBeta\User\Preferences\Application\Handler\GetMyAppearanceSettingsHandler;
use LectoresBeta\User\Preferences\Application\Handler\UpdateAppearanceSettingsHandler;
use LectoresBeta\User\Preferences\Application\Query\GetMyAppearanceSettings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET` y `PUT /api/v1/me/appearance-settings` (`FEAT-USR-042`).
 *
 * El tema se guarda en el servidor y no en el navegador porque **tiene que
 * sobrevivir al dispositivo**: quien eligió oscuro en el portátil y abre el
 * móvil espera oscuro.
 *
 * Y viaja además en el contexto de sesión, que es lo que evita pintar la
 * pantalla en claro y cambiarla medio segundo después.
 *
 * Las dos operaciones comparten controlador porque comparten el recurso.
 */
#[AsController]
final readonly class AppearanceSettingsController
{
    public function __construct(
        private GetMyAppearanceSettingsHandler $mine,
        private UpdateAppearanceSettingsHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $view = $request->isMethod('PUT')
            ? ($this->update)(new UpdateAppearanceSettings(
                $user->getUserIdentifier(),
                JsonBody::of($request)->string('theme'),
            ))
            : ($this->mine)(new GetMyAppearanceSettings($user->getUserIdentifier()));

        return new JsonResponse([
            'theme' => $view->theme->value,
            'updatedAt' => $view->updatedAt->format(\DATE_ATOM),
        ]);
    }
}
