<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Rating\Infrastructure\Controller;

use LectoresBeta\Feedback\Rating\Application\Command\RateWork;
use LectoresBeta\Feedback\Rating\Application\Handler\RateWorkHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/works/{workId}/rating` (`FEAT-FBK-002`).
 *
 * `PUT` y no `POST` porque **fija un estado**: hay una valoración por lector y
 * obra, y volver a valorar sustituye la anterior. Cambiar de opinión después
 * de leer más capítulos es exactamente lo que se espera.
 */
#[AsController]
final readonly class RateWorkController
{
    public function __construct(
        private RateWorkHandler $rate,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse([
            'rating' => ($this->rate)(new RateWork(
                $user->getUserIdentifier(),
                $workId,
                JsonBody::of($request)->int('rating'),
            )),
        ]);
    }
}
