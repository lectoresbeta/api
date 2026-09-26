<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Application\Service;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Invitation\Domain\Event\PlatformInvitationConsumed;
use LectoresBeta\User\Invitation\Domain\Repository\PlatformInvitationRepository;

/**
 * Apuntar quién invitó a quién, al registrarse (`FEAT-USR-018`,
 * `FEAT-USR-001` `RN-12`).
 *
 * **Un token inválido o ya gastado nunca impide un alta** (`RN-13`). Se
 * ignora en silencio, y es deliberado: quien llega con un enlace viejo viene
 * a registrarse, no a canjear nada, y rechazar su cuenta por eso sería
 * castigarle por un detalle que no controla.
 *
 * **Consumir no paga.** Solo deja constancia del par; los +5 créditos llegan
 * cuando el invitado entrega su primera corrección (`FEAT-CRD-005`), que es
 * todo el diseño antifraude: falsear esto cuesta una corrección de verdad.
 */
final readonly class ConsumeInvitation
{
    public function __construct(
        private PlatformInvitationRepository $invitations,
        private SecureTokenFactory $tokens,
        private TransactionalSession $session,
        private EventPublisher $events,
    ) {
    }

    public function by(UserId $invitee, ?string $token, \DateTimeImmutable $now): void
    {
        if (null === $token || '' === trim($token)) {
            return;
        }

        $invitation = $this->invitations->ofTokenHash($this->tokens->hashOf(trim($token)));

        if (null === $invitation) {
            return;
        }

        // Nadie se invita a sí mismo, ni siquiera con su propio enlace: el
        // par quedaría apuntado y `Credits` acabaría pagándole por corregir.
        // Se mira **antes** de consumir: marcar la invitación y no guardarla
        // deja la entidad sucia en la sesión de Doctrine, y el siguiente
        // `flush` de la petición —el alta misma— la escribiría igualmente.
        if ($invitation->inviterId()->equals($invitee)) {
            return;
        }

        if (!$invitation->consume($invitee, $now)) {
            return;
        }

        $this->session->execute(function () use ($invitation): void {
            $this->invitations->save($invitation);
        });

        $this->events->publish(new PlatformInvitationConsumed(
            EventId::generate(),
            $invitation->id()->value(),
            $invitation->inviterId()->value(),
            $invitee->value(),
            $now,
        ));
    }
}
