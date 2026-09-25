<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\Command\RateCorrection;
use LectoresBeta\Feedback\Correction\Application\Handler\RateCorrectionHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/corrections/{correctionId}/rating` (`FEAT-FBK-006`).
 *
 * `helpful: true | false`. **Binaria**, que es lo que `F-5` resuelve: una
 * escala fina invita a puntuar a la baja por desacuerdo literario, y la
 * pregunta aquí es «¿te ha servido?».
 *
 * Retirar la valoración por completo —volver a «sin valorar»— no está
 * previsto (`F-19`).
 */
#[AsController]
final readonly class RateCorrectionController
{
    public function __construct(
        private RateCorrectionHandler $rate,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $correctionId): Response
    {
        $author = $this->security->getUser();

        if (null === $author) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $helpful = JsonBody::of($request)->bool('helpful');

        if (null === $helpful) {
            throw new BadRequestHttpException('The field «helpful» must be true or false.');
        }

        ($this->rate)(new RateCorrection($correctionId, $author->getUserIdentifier(), $helpful));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
