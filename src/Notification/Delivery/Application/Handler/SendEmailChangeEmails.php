<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\DTO\EmailMessage;
use LectoresBeta\Notification\Delivery\Application\Event\EmailChangeRequested;
use LectoresBeta\Notification\Delivery\Application\Port\Mailer;
use LectoresBeta\Notification\Delivery\Domain\Entity\Notification;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\EmailChangeLinkProvider;

/**
 * Los **dos** correos de un cambio de dirección (`FEAT-USR-040`).
 *
 * Dos, y ninguno sobra:
 *
 * - **el enlace, a la dirección nueva**, que es lo que demuestra que ese
 *   buzón existe y es suyo;
 * - **el aviso, a la anterior** (`RN-3`), que es la única defensa de quien ha
 *   perdido el control de su sesión. Sin él, este flujo se limita a verificar
 *   que el correo nuevo existe, que es justo lo que un atacante puede
 *   demostrar.
 *
 * Los dos son **operativos**: ignoran toda preferencia, incluido el
 * interruptor general. Quien se diera de baja del segundo no se enteraría de
 * que le están quitando la cuenta.
 *
 * Se mandan en ese orden a propósito. Si el segundo falla, quien pidió el
 * cambio al menos tiene su enlace; al revés, el aviso saldría por un cambio
 * que quizá nunca llegó a poder confirmarse.
 */
final readonly class SendEmailChangeEmails
{
    public function __construct(
        private EmailChangeLinkProvider $links,
        private NotificationRepository $notifications,
        private Mailer $mailer,
        private TransactionalSession $session,
        private Clock $clock,
        private string $confirmUrlTemplate,
    ) {
    }

    public function __invoke(EmailChangeRequested $event): void
    {
        $recipient = RecipientId::fromString($event->userId);

        if ($this->notifications->existsFor($recipient, NotificationKind::EMAIL_CHANGE_REQUESTED, $event->eventId())) {
            return;
        }

        $link = $this->links->issueFor($event->requestId);

        if (null === $link) {
            // La solicitud ya no está viva: la anuló otra posterior, o ya se
            // confirmó. Desenlace normal de un evento reentregado.
            return;
        }

        $url = str_replace('{token}', rawurlencode($link->token), $this->confirmUrlTemplate);

        // Anotado antes de enviar: al revés, una caída entre las dos cosas
        // acuñaría un token nuevo en el segundo intento y dejaría sin valor
        // el enlace que ya hubiera salido.
        $this->session->execute(function () use ($recipient, $event, $link): void {
            $this->notifications->save(new Notification(
                NotificationId::generate(),
                $recipient,
                NotificationKind::EMAIL_CHANGE_REQUESTED,
                $this->clock->now(),
                ['expiresAt' => $link->expiresAt->format(\DATE_ATOM)],
                $event->eventId(),
            ));
        });

        $this->mailer->send(new EmailMessage(
            $link->newEmail,
            'Confirma tu nuevo correo en Lectores Beta',
            self::confirmText($link->username, $url),
            self::confirmHtml($link->username, $url),
        ));

        $this->mailer->send(new EmailMessage(
            $link->previousEmail,
            'Se ha pedido cambiar el correo de tu cuenta',
            self::warningText($link->username, $link->newEmail),
            self::warningHtml($link->username, $link->newEmail),
        ));
    }

    private static function confirmText(string $username, string $url): string
    {
        return <<<TEXT
            Hola, {$username}:

            Has pedido usar esta dirección para tu cuenta de Lectores Beta.
            Confírmalo desde este enlace:

            {$url}

            Hasta que lo hagas, tu cuenta sigue usando la dirección anterior.
            TEXT;
    }

    private static function confirmHtml(string $username, string $url): string
    {
        $username = htmlspecialchars($username, \ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($url, \ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <p>Hola, {$username}:</p>
            <p>Has pedido usar esta dirección para tu cuenta de Lectores Beta. Confírmalo desde este enlace:</p>
            <p><a href="{$url}">CONFIRMAR MI NUEVO CORREO</a></p>
            <p>Hasta que lo hagas, tu cuenta sigue usando la dirección anterior.</p>
            HTML;
    }

    /**
     * El aviso lleva la dirección nueva **enmascarada**: quien lo recibe
     * necesita reconocerla o no reconocerla, y no hace falta escribirla
     * entera para eso. Si el aviso llega a un buzón ajeno —reenviado, o en un
     * dispositivo compartido— tampoco hace falta regalar la dirección a la
     * que están intentando llevarse la cuenta.
     */
    private static function warningText(string $username, string $newEmail): string
    {
        $masked = self::mask($newEmail);

        return <<<TEXT
            Hola, {$username}:

            Se ha pedido cambiar el correo de tu cuenta de Lectores Beta a {$masked}.
            El cambio no se aplicará hasta que se confirme desde esa dirección.

            Si no has sido tú, cambia tu contraseña ahora mismo: alguien tiene acceso
            a tu cuenta.
            TEXT;
    }

    private static function warningHtml(string $username, string $newEmail): string
    {
        $username = htmlspecialchars($username, \ENT_QUOTES, 'UTF-8');
        $masked = htmlspecialchars(self::mask($newEmail), \ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <p>Hola, {$username}:</p>
            <p>Se ha pedido cambiar el correo de tu cuenta de Lectores Beta a {$masked}. El cambio no se aplicará hasta que se confirme desde esa dirección.</p>
            <p>Si no has sido tú, cambia tu contraseña ahora mismo: alguien tiene acceso a tu cuenta.</p>
            HTML;
    }

    private static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($local, 0, 1);
        $hidden = str_repeat('*', max(1, mb_strlen($local) - 1));

        return \sprintf('%s%s@%s', $visible, $hidden, $domain);
    }
}
