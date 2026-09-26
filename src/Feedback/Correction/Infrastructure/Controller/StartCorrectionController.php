<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\Command\StartCorrection;
use LectoresBeta\Feedback\Correction\Application\Handler\StartCorrectionHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/chapters/{chapterId}/corrections/start` (`FEAT-FBK-003`).
 *
 * «Empezar corrección». Es una escritura y no un efecto lateral de leer el
 * cuestionario: **ocupa uno de los tres sitios del capítulo y fija el
 * precio**, y un `GET` que hiciera eso convertiría recargar la página en
 * cerrar un capítulo.
 *
 * En una obra `PUBLIC` esta llamada **es** el permiso: no hay solicitud
 * previa ni espera (`R-4`).
 */
#[AsController]
final readonly class StartCorrectionController
{
    public function __construct(
        private StartCorrectionHandler $start,
        private Security $security,
    ) {
    }

    public function __invoke(string $chapterId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $correctionId = ($this->start)(new StartCorrection($chapterId, $user->getUserIdentifier()));

        return new JsonResponse(['correctionId' => $correctionId], Response::HTTP_CREATED);
    }
}
