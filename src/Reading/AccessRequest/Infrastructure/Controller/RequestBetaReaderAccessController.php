<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Infrastructure\Controller;

use LectoresBeta\Reading\AccessRequest\Application\Command\RequestBetaReaderAccess;
use LectoresBeta\Reading\AccessRequest\Application\Handler\RequestBetaReaderAccessHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/works/{workId}/access-requests` (`FEAT-RDG-002`).
 *
 * El camino de entrada a una obra `ON_REQUEST`, que es la modalidad con la
 * que nace toda obra: hasta que esto existió, **publicar no abría la obra a
 * nadie**.
 */
#[AsController]
final readonly class RequestBetaReaderAccessController
{
    public function __construct(
        private RequestBetaReaderAccessHandler $request,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $created = ($this->request)(new RequestBetaReaderAccess(
            $workId,
            $user->getUserIdentifier(),
            JsonBody::of($request)->string('message'),
        ));

        return new JsonResponse([
            'requestId' => $created->id()->value(),
            'status' => $created->status()->value,
        ], Response::HTTP_CREATED);
    }
}
