<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\DTO\EmailMessage;
use LectoresBeta\Notification\Delivery\Application\Event\UserRegistered;
use LectoresBeta\Notification\Delivery\Application\Port\Mailer;
use LectoresBeta\Notification\Delivery\Domain\Entity\Notification;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\ActivationLinkProvider;

/**
 * The activation email (`FEAT-NOT-008`).
 *
 * The token is not in the event and never was: a live credential has no place
 * on a queue that retries, persists and parks messages where people can read
 * them. It is fetched through `User`'s published contract at the moment of
 * sending (`decision:0014`), which is also why the link does not start
 * expiring while the message waits in a retry.
 *
 * **It is transactional mail, not a notification** (`RN-4`, `RN-7`): it
 * ignores every preference, including the master switch, and its footer
 * carries no unsubscribe link. Somebody who unsubscribed from the message
 * that activates their account could never use the account.
 *
 * What is deliberately *not* recorded is the token. Not in the notice's
 * payload, not in a log, not in a metric (`RN-5`).
 */
final readonly class SendActivationEmail
{
    public function __construct(
        private ActivationLinkProvider $activationLinks,
        private NotificationRepository $notifications,
        private Mailer $mailer,
        private TransactionalSession $session,
        private Clock $clock,
        private string $activationUrlTemplate,
    ) {
    }

    public function __invoke(UserRegistered $event): void
    {
        $recipient = RecipientId::fromString($event->userId);

        if ($this->notifications->existsFor($recipient, NotificationKind::ACCOUNT_ACTIVATION, $event->eventId())) {
            return;
        }

        $link = $this->activationLinks->issueFor($event->userId);

        if (null === $link) {
            // No such account, or already active. The normal outcome of a
            // redelivered event, not a failure: nothing to send.
            return;
        }

        $url = str_replace('{token}', rawurlencode($link->token), $this->activationUrlTemplate);

        // Recorded before sending. The other order would let a crash between
        // the two send a second email, and a duplicate activation email is
        // worse than a missing record: the first link stops working.
        $this->session->execute(function () use ($recipient, $event, $link): void {
            $this->notifications->save(new Notification(
                NotificationId::generate(),
                $recipient,
                NotificationKind::ACCOUNT_ACTIVATION,
                $this->clock->now(),
                ['expiresAt' => $link->expiresAt->format(\DATE_ATOM)],
                $event->eventId(),
            ));
        });

        $this->mailer->send(new EmailMessage(
            $link->email,
            'Activa tu cuenta en Lectores Beta',
            self::text($link->username, $url),
            self::html($link->username, $url),
        ));
    }

    private static function text(string $username, string $url): string
    {
        return <<<TEXT
            Hola, {$username}:

            Ya casi está. Activa tu cuenta desde este enlace:

            {$url}

            Si no has creado ninguna cuenta en Lectores Beta, puedes ignorar este correo.
            TEXT;
    }

    private static function html(string $username, string $url): string
    {
        $username = htmlspecialchars($username, \ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($url, \ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <p>Hola, {$username}:</p>
            <p>Ya casi está. Activa tu cuenta desde este enlace:</p>
            <p><a href="{$url}">ACTIVAR MI CUENTA</a></p>
            <p>Si no has creado ninguna cuenta en Lectores Beta, puedes ignorar este correo.</p>
            HTML;
    }
}
