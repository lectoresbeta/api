<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Invitation\Application\Contract\InvitationLink;
use LectoresBeta\User\Invitation\Application\Contract\InvitationLinkProvider;
use LectoresBeta\User\Invitation\Domain\Repository\PlatformInvitationRepository;
use LectoresBeta\User\Invitation\Domain\ValueObject\PlatformInvitationId;

/**
 * El enlace de una invitación, pedido al enviar el correo (`FEAT-NOT-007`).
 *
 * **Vuelve a comprobarlo todo**, y no por desconfiar de quien llama: entre
 * que el hecho se publicó y que el correo sale pueden pasar horas de
 * reintentos, y en ese rato la persona invitada ha podido registrarse por su
 * cuenta. Mandarle entonces una invitación es, como poco, raro.
 *
 * **El token en claro no se puede recuperar**: en la tabla solo está su hash.
 * Así que aquí se emite uno nuevo y sustituye al anterior, con el mismo
 * efecto que en la activación — solo el último correo funciona.
 */
final readonly class IssueInvitationLink implements InvitationLinkProvider
{
    public function __construct(
        private PlatformInvitationRepository $invitations,
        private UserRepository $users,
        private ReplaceInvitationToken $tokens,
    ) {
    }

    public function issueFor(string $invitationId): ?InvitationLink
    {
        try {
            $invitation = $this->invitations->ofId(PlatformInvitationId::fromString($invitationId));
        } catch (InvalidValue) {
            return null;
        }

        if (null === $invitation || !$invitation->isAvailable() || null === $invitation->email()) {
            return null;
        }

        $email = Email::fromString($invitation->email());

        // Se registró por su cuenta mientras el correo esperaba: ya no hay
        // nada que mandar, y quien llama lo trata como «hecho».
        if (null !== $this->users->ofEmail($email)) {
            return null;
        }

        $inviter = $this->users->ofId($invitation->inviterId());

        if (null === $inviter) {
            return null;
        }

        return new InvitationLink(
            $email->value(),
            ($this->tokens)($invitation),
            $inviter->name()?->value(),
            $inviter->username()->value(),
        );
    }
}
