<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Infrastructure\Controller;

use LectoresBeta\Reading\AccessInvitation\Application\Command\ResolveInvitation;
use LectoresBeta\Reading\AccessInvitation\Application\Handler\ResolveInvitationHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/beta-reader-invitations/{invitationId}/resolution`
 * (`FEAT-RDG-005`).
 *
 * Un `PUT` con la decisión, igual que al resolver una solicitud: es una
 * transición de estado con dos valores.
 *
 * `DECLINED` y no `REJECTED`, y no es un capricho: un autor **rechaza** la
 * petición de un desconocido; un lector **declina** una oferta que le han
 * hecho. El producto usa las dos palabras para las dos cosas.
 */
#[AsController]
final readonly class ResolveInvitationController
{
    public function __construct(
        private ResolveInvitationHandler $resolve,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $invitationId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $resolved = ($this->resolve)(new ResolveInvitation(
            $invitationId,
            $user->getUserIdentifier(),
            JsonBody::of($request)->string('decision'),
        ));

        return new JsonResponse([
            'invitationId' => $resolved->id()->value(),
            'status' => $resolved->status()->value,
        ]);
    }
}
