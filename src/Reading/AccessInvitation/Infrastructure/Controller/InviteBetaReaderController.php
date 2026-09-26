<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Infrastructure\Controller;

use LectoresBeta\Reading\AccessInvitation\Application\Command\InviteBetaReader;
use LectoresBeta\Reading\AccessInvitation\Application\Handler\InviteBetaReaderHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/works/{workId}/beta-reader-invitations` (`FEAT-RDG-004`).
 *
 * El único camino de entrada a una obra `PRIVATE`, y el único de los tres que
 * empieza por el autor.
 *
 * Se invita **por identificador de usuario**, no por correo: invitar por
 * correo sería otra cosa —una invitación a la plataforma, que es de `User`— y
 * conviene no mezclarlas. Una ofrece leer una obra, la otra ofrece una
 * cuenta.
 */
#[AsController]
final readonly class InviteBetaReaderController
{
    public function __construct(
        private InviteBetaReaderHandler $invite,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        $invitation = ($this->invite)(new InviteBetaReader(
            $workId,
            $user->getUserIdentifier(),
            $body->string('userId'),
            $body->string('message'),
        ));

        return new JsonResponse([
            'invitationId' => $invitation->id()->value(),
            'status' => $invitation->status()->value,
        ], Response::HTTP_CREATED);
    }
}
