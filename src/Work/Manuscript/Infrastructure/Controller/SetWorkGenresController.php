<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Manuscript\Application\Command\SetWorkGenres;
use LectoresBeta\Work\Manuscript\Application\Handler\SetWorkGenresHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/works/{workId}/genres` (`FEAT-WRK-001`).
 *
 * Un `PUT` con la lista entera, como el modo de acceso y el estado: lo que el
 * autor envía es **cómo queda clasificada su obra**, no un cambio
 * incremental. Enviar la lista vacía la deja sin clasificar, que es un estado
 * legítimo — lo que le pasa es que no aparece cuando alguien filtra.
 */
#[AsController]
final readonly class SetWorkGenresController
{
    public function __construct(
        private SetWorkGenresHandler $setGenres,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->setGenres)(new SetWorkGenres(
            $workId,
            $user->getUserIdentifier(),
            JsonBody::of($request)->stringList('genres'),
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
