<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\DTO\EmailMessage;
use LectoresBeta\Notification\Delivery\Application\Event\PasswordResetRequested;
use LectoresBeta\Notification\Delivery\Application\Port\Mailer;
use LectoresBeta\Notification\Delivery\Domain\Entity\Notification;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\PasswordResetLinkProvider;

/**
 * El correo de «he olvidado mi contraseña» (`FEAT-USR-007`).
 *
 * El token no está en el evento y no podía estarlo: durante una hora **es** la
 * cuenta, y una cola que persiste, reintenta y aparca mensajes donde alguien
 * los lee no es sitio para eso. Se pide por el contrato publicado de `User`
 * en el momento de enviar (`decision:0014`), que además hace que el enlace
 * empiece a caducar al salir el correo y no al pedirse — con una hora de
 * vida, la diferencia es media hora si el envío espera en un reintento.
 *
 * **Es correo operativo, no una notificación**: ignora toda preferencia,
 * incluido el interruptor general, y no lleva enlace de baja. Quien se diera
 * de baja de este correo no podría recuperar nunca su cuenta.
 *
 * El texto dice qué hacer **si no has sido tú**, y eso no es relleno: este
 * correo es lo primero que ve alguien a quien están intentando robarle la
 * cuenta, y lo único que necesita saber es que basta con ignorarlo.
 *
 * Lo que no se registra en ningún sitio es el token: ni en el payload del
 * aviso, ni en un log, ni en una métrica.
 */
final readonly class SendPasswordResetEmail
{
    public function __construct(
        private PasswordResetLinkProvider $resetLinks,
        private NotificationRepository $notifications,
        private Mailer $mailer,
        private TransactionalSession $session,
        private Clock $clock,
        private string $resetUrlTemplate,
    ) {
    }

    public function __invoke(PasswordResetRequested $event): void
    {
        $recipient = RecipientId::fromString($event->userId);

        if ($this->notifications->existsFor($recipient, NotificationKind::PASSWORD_RESET_REQUESTED, $event->eventId())) {
            return;
        }

        $link = $this->resetLinks->issueFor($event->userId);

        if (null === $link) {
            // No hay cuenta, o está eliminada. Es el desenlace normal de un
            // evento reentregado, no un fallo: no hay nada que mandar.
            return;
        }

        $url = str_replace('{token}', rawurlencode($link->token), $this->resetUrlTemplate);

        // Se anota antes de enviar. Al revés, una caída entre las dos cosas
        // mandaría un segundo correo con un token nuevo, y el enlace que la
        // persona ya tuviera abierto dejaría de funcionar.
        $this->session->execute(function () use ($recipient, $event, $link): void {
            $this->notifications->save(new Notification(
                NotificationId::generate(),
                $recipient,
                NotificationKind::PASSWORD_RESET_REQUESTED,
                $this->clock->now(),
                ['expiresAt' => $link->expiresAt->format(\DATE_ATOM)],
                $event->eventId(),
            ));
        });

        $this->mailer->send(new EmailMessage(
            $link->email,
            'Restablece tu contraseña en Lectores Beta',
            self::text($link->username, $url),
            self::html($link->username, $url),
        ));
    }

    private static function text(string $username, string $url): string
    {
        return <<<TEXT
            Hola, {$username}:

            Has pedido restablecer tu contraseña. Puedes hacerlo desde este enlace,
            que caduca en una hora:

            {$url}

            Si no has sido tú, ignora este correo: tu contraseña no ha cambiado y
            este enlace caducará solo.
            TEXT;
    }

    private static function html(string $username, string $url): string
    {
        $username = htmlspecialchars($username, \ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($url, \ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <p>Hola, {$username}:</p>
            <p>Has pedido restablecer tu contraseña. Puedes hacerlo desde este enlace, que caduca en una hora:</p>
            <p><a href="{$url}">RESTABLECER MI CONTRASEÑA</a></p>
            <p>Si no has sido tú, ignora este correo: tu contraseña no ha cambiado y este enlace caducará solo.</p>
            HTML;
    }
}
