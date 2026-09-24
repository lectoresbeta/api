<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Infrastructure\Controller;

use LectoresBeta\Reading\BetaReaderAccess\Application\Command\RevokeBetaReaderAccess;
use LectoresBeta\Reading\BetaReaderAccess\Application\Handler\RevokeBetaReaderAccessHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/works/{workId}/beta-readers/{readerId}` (`FEAT-RDG-010`).
 *
 * Sobre **la persona dentro de la obra**, y no sobre un identificador de
 * acceso: lo que el autor quiere decir es «esta persona, fuera de esta obra»,
 * y no tiene por qué saber que existe una fila llamada acceso. De paso, la
 * operación sale idempotente sin esfuerzo.
 */
#[AsController]
final readonly class RevokeBetaReaderAccessController
{
    public function __construct(
        private RevokeBetaReaderAccessHandler $revoke,
        private Security $security,
    ) {
    }

    public function __invoke(string $workId, string $readerId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->revoke)(new RevokeBetaReaderAccess($workId, $readerId, $user->getUserIdentifier()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
