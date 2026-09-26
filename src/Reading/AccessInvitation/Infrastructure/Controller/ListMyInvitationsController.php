<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Infrastructure\Controller;

use LectoresBeta\Reading\AccessInvitation\Application\Handler\ListMyInvitationsHandler;
use LectoresBeta\Reading\AccessInvitation\Application\Query\ListMyInvitations;
use LectoresBeta\Reading\AccessInvitation\Infrastructure\Http\InvitationBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/beta-reader-invitations` (`FEAT-RDG-005`).
 *
 * Lleva sinopsis y clasificación de contenido de cada obra, y ahí está su
 * razón de ser: **es el único sitio del producto donde alguien decide leer
 * algo sin haberlo visto en el catálogo**, porque la obra puede ser privada o
 * un borrador.
 */
#[AsController]
final readonly class ListMyInvitationsController
{
    public function __construct(
        private ListMyInvitationsHandler $invitations,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(InvitationBody::of(($this->invitations)(new ListMyInvitations(
            $user->getUserIdentifier(),
            InvitationBody::optional($request, 'status'),
            InvitationBody::optional($request, 'cursor'),
            InvitationBody::limit($request),
        ))));
    }
}
