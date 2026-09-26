<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Application\Service;

use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Invitation\Domain\Entity\PlatformInvitation;
use LectoresBeta\User\Invitation\Domain\Repository\PlatformInvitationRepository;

/**
 * Un token nuevo para una invitación, que invalida el anterior
 * (`FEAT-NOT-007`).
 *
 * **Solo el último correo funciona**, igual que con la activación. Es lo que
 * hace seguro reenviar una invitación: el enlace viejo deja de valer en el
 * momento en que sale el nuevo.
 */
final readonly class ReplaceInvitationToken
{
    public function __construct(
        private PlatformInvitationRepository $invitations,
        private SecureTokenFactory $tokens,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(PlatformInvitation $invitation): string
    {
        $token = $this->tokens->create();
        $invitation->replaceToken($token->hash);

        $this->session->execute(function () use ($invitation): void {
            $this->invitations->save($invitation);
        });

        return $token->plain;
    }
}
