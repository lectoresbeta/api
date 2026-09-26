<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Infrastructure\Mail;

use LectoresBeta\Notification\Delivery\Application\DTO\EmailMessage;
use LectoresBeta\Notification\Delivery\Application\Port\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * The port, over Symfony Mailer. The provider lives in `MAILER_DSN`, so
 * changing it is configuration and not code (`FEAT-NOT-008` `N-3`).
 */
final readonly class SymfonyMailer implements Mailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $sender,
    ) {
    }

    public function send(EmailMessage $message): void
    {
        $this->mailer->send(
            (new Email())
                ->from($this->sender)
                ->to($message->to)
                ->subject($message->subject)
                ->text($message->text)
                ->html($message->html),
        );
    }
}
