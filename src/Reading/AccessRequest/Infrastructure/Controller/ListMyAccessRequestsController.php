<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Infrastructure\Controller;

use LectoresBeta\Reading\AccessRequest\Application\Handler\ListMyAccessRequestsHandler;
use LectoresBeta\Reading\AccessRequest\Application\Query\ListMyAccessRequests;
use LectoresBeta\Reading\AccessRequest\Infrastructure\Http\AccessRequestBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/access-requests` (`FEAT-RDG-002`).
 */
#[AsController]
final readonly class ListMyAccessRequestsController
{
    public function __construct(
        private ListMyAccessRequestsHandler $requests,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(AccessRequestBody::of(($this->requests)(new ListMyAccessRequests(
            $user->getUserIdentifier(),
            AccessRequestBody::optional($request, 'status'),
            AccessRequestBody::optional($request, 'cursor'),
            AccessRequestBody::limit($request),
        ))));
    }
}
