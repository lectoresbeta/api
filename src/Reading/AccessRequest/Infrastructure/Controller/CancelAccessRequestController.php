<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Infrastructure\Controller;

use LectoresBeta\Reading\AccessRequest\Application\Command\CancelAccessRequest;
use LectoresBeta\Reading\AccessRequest\Application\Handler\CancelAccessRequestHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/access-requests/{requestId}` (`FEAT-RDG-002` `RN-7`).
 *
 * Retirarse de la cola. No avisa al autor: nadie estaba esperando una
 * pregunta que quien la hizo ha retirado.
 */
#[AsController]
final readonly class CancelAccessRequestController
{
    public function __construct(
        private CancelAccessRequestHandler $cancel,
        private Security $security,
    ) {
    }

    public function __invoke(string $requestId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->cancel)(new CancelAccessRequest($requestId, $user->getUserIdentifier()));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
