<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Handler;

use LectoresBeta\Reading\AccessInvitation\Application\Command\CancelInvitation;
use LectoresBeta\Reading\AccessInvitation\Domain\Exception\InvitationNotFound;
use LectoresBeta\Reading\AccessInvitation\Domain\Exception\InvitationRefused;
use LectoresBeta\Reading\AccessInvitation\Domain\Repository\AccessInvitationRepository;
use LectoresBeta\Reading\AccessInvitation\Domain\ValueObject\AccessInvitationId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Retirar la oferta (`FEAT-RDG-004` `RN-9`).
 *
 * **No publica nada**, y es discutible: el invitado ya recibió el aviso y
 * puede llegar a una oferta que ya no está. Se ha preferido no inventar un
 * evento cuyo consumidor no está especificado, y que quien llegue tarde
 * reciba un `409` claro. Queda apuntado como `R-14`.
 */
final readonly class CancelInvitationHandler
{
    public function __construct(
        private AccessInvitationRepository $invitations,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(CancelInvitation $command): void
    {
        try {
            $invitation = $this->invitations->ofId(AccessInvitationId::fromString($command->invitationId));
        } catch (InvalidValue) {
            throw InvitationNotFound::invitation();
        }

        if (null === $invitation || $invitation->authorId()->value() !== $command->authorId) {
            throw InvitationNotFound::invitation();
        }

        if (!$invitation->isOpen()) {
            throw InvitationRefused::alreadyResolved();
        }

        $this->session->execute(function () use ($invitation): void {
            $invitation->cancel($this->clock->now());
            $this->invitations->save($invitation);
        });
    }
}
