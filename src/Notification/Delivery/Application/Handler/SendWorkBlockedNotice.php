<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\DTO\EmailMessage;
use LectoresBeta\Notification\Delivery\Application\Event\WorkBlockedByModeration;
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
 * «Tu obra ha sido bloqueada» (`FEAT-MOD-003` `RN-8b`).
 *
 * **Es la única vía por la que el autor se entera y la única por la que puede
 * reaccionar**, así que es operativo: se envía aunque tenga las
 * notificaciones desactivadas (`FEAT-USR-039` `RN-3`).
 *
 * Lleva las tres cosas, y ninguna sobra:
 *
 * - **qué** se ha bloqueado, capítulo concreto u obra entera;
 * - **por qué**, que es el motivo de la reclamación estimada y no la
 *   motivación interna del moderador;
 * - **a qué dirección escribir** para recurrir. Sin esto el recurso existe
 *   sobre el papel y no en la práctica: un recurso que no se sabe dónde
 *   presentar no existe.
 *
 * No hay formulario de recurso ni cola de apelaciones en la plataforma
 * (`RN-8`), a propósito: el recurso es por correo.
 */
final readonly class SendWorkBlockedNotice
{
    private const CHAPTER = 'CHAPTER';

    public function __construct(
        private MailingAddresses $addresses,
        private NotificationRepository $notifications,
        private Mailer $mailer,
        private TransactionalSession $session,
        private Clock $clock,
        private string $appealsAddress,
    ) {
    }

    public function __invoke(WorkBlockedByModeration $event): void
    {
        $recipient = RecipientId::fromString($event->authorId);

        if ($this->notifications->existsFor($recipient, NotificationKind::WORK_BLOCKED, $event->eventId())) {
            return;
        }

        $address = $this->addresses->ofUser($event->authorId);

        if (null === $address) {
            return;
        }

        $this->session->execute(function () use ($recipient, $event): void {
            $this->notifications->save(new Notification(
                NotificationId::generate(),
                $recipient,
                NotificationKind::WORK_BLOCKED,
                $this->clock->now(),
                [
                    'workId' => $event->workId,
                    'chapterId' => $event->chapterId,
                    'scope' => $event->scope,
                    'reason' => $event->reason,
                ],
                $event->eventId(),
            ));
        });

        $what = self::CHAPTER === $event->scope
            ? \sprintf('un capítulo de «%s»', $event->title)
            : \sprintf('tu obra «%s»', $event->title);

        $this->mailer->send(new EmailMessage(
            $address->email,
            'Se ha bloqueado contenido tuyo en Lectores Beta',
            self::text($address->username, $what, $event->reason, $this->appealsAddress),
            self::html($address->username, $what, $event->reason, $this->appealsAddress),
        ));
    }

    private static function text(string $username, string $what, string $reason, string $appeals): string
    {
        return <<<TEXT
            Hola, {$username}:

            Hemos bloqueado {$what} tras estimar una reclamación ({$reason}).

            Deja de estar accesible para el resto de personas. Tú la sigues
            viendo, marcada como bloqueada, y no se ha borrado nada.

            Si no estás de acuerdo, escríbenos a {$appeals} y lo revisamos.
            TEXT;
    }

    private static function html(string $username, string $what, string $reason, string $appeals): string
    {
        $username = htmlspecialchars($username, \ENT_QUOTES, 'UTF-8');
        $what = htmlspecialchars($what, \ENT_QUOTES, 'UTF-8');
        $reason = htmlspecialchars($reason, \ENT_QUOTES, 'UTF-8');
        $appeals = htmlspecialchars($appeals, \ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <p>Hola, {$username}:</p>
            <p>Hemos bloqueado {$what} tras estimar una reclamación ({$reason}).</p>
            <p>Deja de estar accesible para el resto de personas. Tú la sigues viendo, marcada como bloqueada, y no se ha borrado nada.</p>
            <p>Si no estás de acuerdo, escríbenos a <a href="mailto:{$appeals}">{$appeals}</a> y lo revisamos.</p>
            HTML;
    }
}
