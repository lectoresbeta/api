<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Infrastructure\Controller;

use LectoresBeta\User\Invitation\Application\DTO\SentInvitation;
use LectoresBeta\User\Invitation\Application\Handler\ListMyInvitationsHandler;
use LectoresBeta\User\Invitation\Application\Query\ListMyInvitations;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/invitations` (`FEAT-USR-018`).
 *
 * Solo las propias, y no hay variante para ver las de otro: a quién invita
 * alguien es de las cosas más privadas que guarda la plataforma.
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

        $sent = ($this->invitations)(new ListMyInvitations(
            $user->getUserIdentifier(),
            $request->query->getInt('limit', 50),
            $request->query->getInt('offset'),
        ));

        return new JsonResponse([
            'invitations' => array_map(
                static fn (SentInvitation $one): array => [
                    'invitationId' => $one->invitationId,
                    'email' => $one->email,
                    'accepted' => $one->accepted,
                    'sentAt' => $one->sentAt,
                    'acceptedAt' => $one->acceptedAt,
                ],
                $sent,
            ),
        ]);
    }
}
