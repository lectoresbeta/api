<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Infrastructure\Controller;

use LectoresBeta\Reading\AccessRequest\Application\Handler\ListWorkAccessRequestsHandler;
use LectoresBeta\Reading\AccessRequest\Application\Query\ListWorkAccessRequests;
use LectoresBeta\Reading\AccessRequest\Infrastructure\Http\AccessRequestBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/works/{workId}/access-requests` (`FEAT-RDG-003`).
 *
 * La bandeja del autor. Lleva el mensaje de cada solicitante, que es lo
 * único que convierte una lista de identificadores en una decisión.
 */
#[AsController]
final readonly class ListWorkAccessRequestsController
{
    public function __construct(
        private ListWorkAccessRequestsHandler $requests,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(AccessRequestBody::of(($this->requests)(new ListWorkAccessRequests(
            $workId,
            $user->getUserIdentifier(),
            AccessRequestBody::optional($request, 'status'),
            AccessRequestBody::optional($request, 'cursor'),
            AccessRequestBody::limit($request),
        ))));
    }
}
