<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\DTO\EmailMessage;
use LectoresBeta\Notification\Delivery\Application\Event\PasswordChanged;
use LectoresBeta\Notification\Delivery\Application\Port\Mailer;
use LectoresBeta\Notification\Delivery\Domain\Entity\Notification;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\MailingAddresses;

/**
 * «Tu contraseña ha cambiado» (`FEAT-USR-007` `RN-11`, `FEAT-USR-041`
 * `RN-4`).
 *
 * **Es el aviso que hace que robar una cuenta se note.** Si alguien entra y
 * cambia la contraseña, este correo es lo único que se lo dice a su dueño, y
 * por eso es operativo: ignora toda preferencia, incluido el interruptor
 * general, y no lleva enlace de baja.
 *
 * Llega igual por los dos caminos —cambiarla desde dentro y restablecerla
 * desde el correo— porque quien no hizo ninguna de las dos cosas necesita
 * enterarse en los dos casos. Lo que cambia es la frase: `viaReset` distingue
 * «has cambiado tu contraseña» de «tu contraseña se ha restablecido», y para
 * quien no hizo nada no significan lo mismo.
 *
 * No lleva la contraseña, ni su hash, ni un enlace para deshacerlo. Un enlace
 * aquí sería una credencial más volando por correo justo cuando puede haber
 * alguien leyéndolo.
 */
final readonly class SendPasswordChangedNotice
{
    public function __construct(
        private MailingAddresses $addresses,
        private NotificationRepository $notifications,
        private Mailer $mailer,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(PasswordChanged $event): void
    {
        $recipient = RecipientId::fromString($event->userId);

        if ($this->notifications->existsFor($recipient, NotificationKind::PASSWORD_CHANGED, $event->eventId())) {
            return;
        }

        $address = $this->addresses->ofUser($event->userId);

        if (null === $address) {
            return;
        }

        $this->session->execute(function () use ($recipient, $event): void {
            $this->notifications->save(new Notification(
                NotificationId::generate(),
                $recipient,
                NotificationKind::PASSWORD_CHANGED,
                $this->clock->now(),
                ['viaReset' => $event->viaReset],
                $event->eventId(),
            ));
        });

        $this->mailer->send(new EmailMessage(
            $address->email,
            'Tu contraseña de Lectores Beta ha cambiado',
            self::text($address->username, $event->viaReset),
            self::html($address->username, $event->viaReset),
        ));
    }

    private static function text(string $username, bool $viaReset): string
    {
        $what = $viaReset
            ? 'Tu contraseña se ha restablecido desde el enlace que pediste por correo.'
            : 'Has cambiado la contraseña de tu cuenta.';

        return <<<TEXT
            Hola, {$username}:

            {$what} Además se han cerrado las demás sesiones.

            Si no has sido tú, alguien tiene acceso a tu cuenta. Recupérala desde
            «He olvidado mi contraseña» y escríbenos.
            TEXT;
    }

    private static function html(string $username, bool $viaReset): string
    {
        $username = htmlspecialchars($username, \ENT_QUOTES, 'UTF-8');
        $what = $viaReset
            ? 'Tu contraseña se ha restablecido desde el enlace que pediste por correo.'
            : 'Has cambiado la contraseña de tu cuenta.';

        return <<<HTML
            <p>Hola, {$username}:</p>
            <p>{$what} Además se han cerrado las demás sesiones.</p>
            <p>Si no has sido tú, alguien tiene acceso a tu cuenta. Recupérala desde «He olvidado mi contraseña» y escríbenos.</p>
            HTML;
    }
}
