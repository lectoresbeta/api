<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Infrastructure\Controller;

use LectoresBeta\Reading\AccessRequest\Application\Command\ResolveAccessRequest;
use LectoresBeta\Reading\AccessRequest\Application\Handler\ResolveAccessRequestHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/access-requests/{requestId}/resolution` (`FEAT-RDG-003`).
 *
 * Un `PUT` con la decisión, y no dos rutas `accept` y `reject`: es **una**
 * transición de estado con dos valores posibles, igual que el estado de una
 * obra. Dos rutas para el mismo cambio obligan a mantener dos veces las
 * mismas comprobaciones, y son dos sitios donde olvidarse de una.
 */
#[AsController]
final readonly class ResolveAccessRequestController
{
    public function __construct(
        private ResolveAccessRequestHandler $resolve,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $requestId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $resolved = ($this->resolve)(new ResolveAccessRequest(
            $requestId,
            $user->getUserIdentifier(),
            JsonBody::of($request)->string('decision'),
        ));

        return new JsonResponse([
            'requestId' => $resolved->id()->value(),
            'status' => $resolved->status()->value,
        ]);
    }
}
