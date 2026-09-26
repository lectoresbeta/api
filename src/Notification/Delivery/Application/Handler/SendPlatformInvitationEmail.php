<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\DTO\EmailMessage;
use LectoresBeta\Notification\Delivery\Application\Event\PlatformInvitationSent;
use LectoresBeta\Notification\Delivery\Application\Port\Mailer;
use LectoresBeta\Notification\Delivery\Domain\Entity\Notification;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Invitation\Application\Contract\InvitationLinkProvider;

/**
 * El correo de invitación a la plataforma (`FEAT-NOT-007`).
 *
 * `PLATFORM_INVITATION` llevaba en el catálogo desde `FEAT-NOT-001` **sin
 * nadie que lo disparara**. Esto lo enciende.
 *
 * Mismo patrón que el correo de activación, y por las mismas razones: el
 * token no viaja en el hecho —es una credencial viva— sino que se pide a
 * `User` en el momento de enviar, así que el enlace no empieza a caducar
 * mientras el mensaje espera en un reintento.
 *
 * **Va a alguien que no tiene cuenta**, y eso lo hace distinto de todo lo
 * demás que manda este contexto:
 *
 * - el destinatario **no es un usuario**, así que la fila del aviso se
 *   apunta a nombre de **quien invita**. Es de quien es la acción, y es el
 *   único que existe en la plataforma;
 * - no se le pueden aplicar preferencias de notificación porque no tiene
 *   ninguna. Es correo transaccional: lo pidió alguien, va una vez, y no se
 *   repite salvo que vuelvan a pedirlo.
 *
 * Si al llegar aquí ya no hay nada que mandar —la invitación se usó, o esa
 * dirección se registró por su cuenta mientras el correo esperaba— el
 * contrato responde `null` y esto no hace nada. Es el desenlace normal de un
 * hecho reentregado, no un fallo.
 */
final readonly class SendPlatformInvitationEmail
{
    public function __construct(
        private InvitationLinkProvider $links,
        private NotificationRepository $notifications,
        private Mailer $mailer,
        private TransactionalSession $session,
        private Clock $clock,
        private string $invitationUrlTemplate,
    ) {
    }

    public function __invoke(PlatformInvitationSent $event): void
    {
        $inviter = RecipientId::fromString($event->inviterId);

        if ($this->notifications->existsFor($inviter, NotificationKind::PLATFORM_INVITATION, $event->eventId())) {
            return;
        }

        $link = $this->links->issueFor($event->invitationId);

        if (null === $link) {
            return;
        }

        $url = str_replace('{token}', rawurlencode($link->token), $this->invitationUrlTemplate);
        $who = $link->inviterName ?? '@'.$link->inviterUsername;

        // Se apunta antes de mandar, igual que la activación: el orden
        // contrario dejaría que una caída entre las dos cosas mandara un
        // segundo correo, y el segundo invalidaría el enlace del primero.
        $this->session->execute(function () use ($inviter, $event): void {
            $this->notifications->save(new Notification(
                NotificationId::generate(),
                $inviter,
                NotificationKind::PLATFORM_INVITATION,
                $this->clock->now(),
                // **Sin la dirección invitada y sin el token.** A quién
                // invita alguien no hace falta guardarlo para nada, y el
                // token no se apunta en ningún sitio.
                ['invitationId' => $event->invitationId],
                $event->eventId(),
            ));
        });

        $this->mailer->send(new EmailMessage(
            $link->email,
            \sprintf('%s te invita a Lectores Beta', $who),
            self::text($who, $url),
            self::html($who, $url),
        ));
    }

    private static function text(string $who, string $url): string
    {
        return <<<TEXT
            Hola:

            {$who} te invita a Lectores Beta, una plataforma donde quien escribe
            encuentra a quien lee de verdad.

            Crea tu cuenta desde este enlace:

            {$url}

            Si no te interesa, puedes ignorar este correo: no volveremos a escribirte.
            TEXT;
    }

    private static function html(string $who, string $url): string
    {
        $who = htmlspecialchars($who, \ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($url, \ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <p>Hola:</p>
            <p><strong>{$who}</strong> te invita a Lectores Beta, una plataforma donde quien
            escribe encuentra a quien lee de verdad.</p>
            <p><a href="{$url}">CREAR MI CUENTA</a></p>
            <p>Si no te interesa, puedes ignorar este correo: no volveremos a escribirte.</p>
            HTML;
    }
}
