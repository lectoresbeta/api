<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\Command\SetCorrectionVisibility;
use LectoresBeta\Feedback\Correction\Application\Handler\SetCorrectionVisibilityHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/corrections/{correctionId}/visibility` (`FEAT-FBK-007`).
 *
 * `PUT` con `hidden` y no dos rutas: **fija un estado**, y las dos
 * direcciones son la misma decisión vista al derecho y al revés. Repetirla no
 * cambia nada.
 *
 * La respuesta devuelve la visibilidad resultante, que es lo que la pantalla
 * necesita para repintar el botón sin una segunda petición.
 */
#[AsController]
final readonly class SetCorrectionVisibilityController
{
    public function __construct(
        private SetCorrectionVisibilityHandler $visibility,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $correctionId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse([
            'visibility' => ($this->visibility)(new SetCorrectionVisibility(
                $user->getUserIdentifier(),
                $correctionId,
                true === JsonBody::of($request)->bool('hidden'),
            )),
        ]);
    }
}
