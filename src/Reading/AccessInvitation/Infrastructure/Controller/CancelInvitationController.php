<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Infrastructure\Controller;

use LectoresBeta\Reading\AccessInvitation\Application\Command\CancelInvitation;
use LectoresBeta\Reading\AccessInvitation\Application\Handler\CancelInvitationHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/beta-reader-invitations/{invitationId}` (`FEAT-RDG-004`
 * `RN-9`).
 *
 * Retirar la oferta mientras siga pendiente. Solo el autor.
 */
#[AsController]
final readonly class CancelInvitationController
{
    public function __construct(
        private CancelInvitationHandler $cancel,
        private Security $security,
    ) {
    }

    public function __invoke(string $invitationId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->cancel)(new CancelInvitation($invitationId, $user->getUserIdentifier()));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
