<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Service;

use LectoresBeta\Notification\Delivery\Application\DTO\EmailMessage;
use LectoresBeta\Notification\Delivery\Application\Port\Mailer;
use LectoresBeta\Notification\Delivery\Domain\Entity\Notification;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\Service\NotificationPhrase;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\MailingAddresses;

/**
 * Sacar un aviso por correo (`FEAT-NOT-002`).
 *
 * **El orden de las tres líneas del final es el punto entero de esta clase.**
 * Se manda, y solo después se anota que se mandó, dentro de su propia
 * transacción. Al revés —anotar y luego mandar— un fallo del proveedor
 * dejaría la fila diciendo que el correo salió cuando no salió, y el
 * reintento del consumidor no lo arreglaría porque se daría por hecho.
 *
 * Así el peor caso es un correo repetido, que es molesto; el otro orden
 * produce un correo perdido, que es el que hace que alguien no se entere de
 * que le han corregido.
 *
 * Fallar **se deja escapar** a propósito: quien llama es un consumidor de
 * cola, así que la excepción hace que RabbitMQ reintregue y, si insiste,
 * aparque el mensaje. Tragársela aquí convertiría una caída del proveedor en
 * silencio.
 */
final readonly class EmailTheNotification
{
    public function __construct(
        private MailingAddresses $addresses,
        private NotificationRepository $notifications,
        private NotificationPhrase $phrase,
        private Mailer $mailer,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function of(Notification $notification): void
    {
        if ($notification->wasEmailed()) {
            return;
        }

        $address = $this->addresses->ofUser($notification->recipientId()->value());

        if (null === $address) {
            // Cuenta eliminada o anonimizada. No hay a quién escribir y
            // reintentar no la va a resucitar (`RN-5`).
            return;
        }

        $kind = $notification->kind();
        $payload = $notification->payload();

        $this->mailer->send(new EmailMessage(
            $address->email,
            $this->phrase->subjectOf($kind, $payload),
            self::text($address->username, $this->phrase->bodyOf($kind, $payload)),
            self::html($address->username, $this->phrase->bodyOf($kind, $payload)),
        ));

        $this->session->execute(function () use ($notification): void {
            $notification->markEmailed($this->clock->now());
            $this->notifications->save($notification);
        });
    }

    private static function text(string $username, string $body): string
    {
        return <<<TEXT
            Hola, {$username}:

            {$body}

            Puedes cambiar qué avisos recibes por correo desde Configuración.
            TEXT;
    }

    private static function html(string $username, string $body): string
    {
        $username = htmlspecialchars($username, \ENT_QUOTES, 'UTF-8');
        $body = htmlspecialchars($body, \ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <p>Hola, {$username}:</p>
            <p>{$body}</p>
            <p>Puedes cambiar qué avisos recibes por correo desde Configuración.</p>
            HTML;
    }
}
