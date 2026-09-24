<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Infrastructure\Controller;

use LectoresBeta\Reading\AccessInvitation\Application\Handler\ListWorkInvitationsHandler;
use LectoresBeta\Reading\AccessInvitation\Application\Query\ListWorkInvitations;
use LectoresBeta\Reading\AccessInvitation\Infrastructure\Http\InvitationBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/works/{workId}/beta-reader-invitations` (`FEAT-RDG-004`).
 */
#[AsController]
final readonly class ListWorkInvitationsController
{
    public function __construct(
        private ListWorkInvitationsHandler $invitations,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(InvitationBody::of(($this->invitations)(new ListWorkInvitations(
            $workId,
            $user->getUserIdentifier(),
            InvitationBody::optional($request, 'status'),
            InvitationBody::optional($request, 'cursor'),
            InvitationBody::limit($request),
        ))));
    }
}
