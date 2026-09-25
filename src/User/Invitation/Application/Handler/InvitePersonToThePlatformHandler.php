<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Invitation\Application\Command\InvitePersonToThePlatform;
use LectoresBeta\User\Invitation\Domain\Entity\PlatformInvitation;
use LectoresBeta\User\Invitation\Domain\Event\PlatformInvitationSent;
use LectoresBeta\User\Invitation\Domain\Exception\InvitationRefused;
use LectoresBeta\User\Invitation\Domain\Repository\PlatformInvitationRepository;
use LectoresBeta\User\Invitation\Domain\ValueObject\PlatformInvitationId;

/**
 * Invitar a alguien a la plataforma (`FEAT-USR-018`).
 *
 * **Responde lo mismo se mande el correo o no**, y es la regla que más
 * importa aquí. Invitar a una dirección que ya tiene cuenta no dice «esa
 * persona ya está»: eso convertiría el formulario de invitar en un
 * comprobador de quién está dentro, que es justo lo que el alta y la
 * recuperación de contraseña se cuidan de no decir. Se acepta, no se manda
 * nada, y quien invita no aprende nada que no supiera.
 *
 * El hecho se publica **solo cuando hay algo que mandar**. Publicarlo
 * siempre y decidir en `Notification` movería esa comprobación a un contexto
 * que no tiene por qué saber quién tiene cuenta.
 *
 * **Con tope diario** (`RN-4`). Sin él, invitar es un canal de correo
 * gratuito hacia direcciones ajenas con el remitente de la plataforma: el
 * tipo de cosa que quema un dominio en una tarde.
 *
 * Reinvitar a quien ya invitaste **no crea una segunda invitación**: la
 * primera sigue valiendo, y dos correos iguales son spam con buena intención.
 */
final readonly class InvitePersonToThePlatformHandler
{
    /**
     * Cuántas al día. Suficiente para traerse a un club de lectura entero;
     * poco para montar un envío masivo.
     */
    public const DAILY_LIMIT = 20;

    public function __construct(
        private UserRepository $users,
        private PlatformInvitationRepository $invitations,
        private SecureTokenFactory $tokens,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(InvitePersonToThePlatform $command): void
    {
        $email = Email::fromString($command->email);
        $inviterId = UserId::fromString($command->inviterId);
        $inviter = $this->users->ofId($inviterId);

        if (null !== $inviter && $inviter->email()->equals($email)) {
            throw InvitationRefused::toYourself();
        }

        $now = $this->clock->now();

        if ($this->invitations->sentCountSince($inviterId, $now->modify('-1 day')) >= self::DAILY_LIMIT) {
            throw InvitationRefused::becauseOfTheDailyLimit(self::DAILY_LIMIT);
        }

        // Ya tiene cuenta, o ya la invitó: en los dos casos se responde que
        // sí y no se manda nada. Quien invita no distingue los tres
        // desenlaces, y eso es lo que impide usar esto para sondear.
        if (
            null !== $this->users->ofEmail($email)
            || null !== $this->invitations->liveTo($inviterId, $email->value())
        ) {
            return;
        }

        $token = $this->tokens->create();
        $invitation = new PlatformInvitation(
            PlatformInvitationId::generate(),
            $inviterId,
            $token->hash,
            $now,
            $email,
        );

        $this->session->execute(function () use ($invitation): void {
            $this->invitations->save($invitation);
        });

        // Sin el token: una credencial viva no entra en una cola que
        // persiste, reintenta y aparca mensajes. El correo lo pide en el
        // momento de enviarlo, por `InvitationLinkProvider`.
        $this->events->publish(new PlatformInvitationSent(
            EventId::generate(),
            $invitation->id()->value(),
            $inviterId->value(),
            $email->value(),
            $now,
        ));
    }
}
