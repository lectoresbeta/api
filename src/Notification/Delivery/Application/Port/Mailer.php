<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Port;

use LectoresBeta\Notification\Delivery\Application\DTO\EmailMessage;

/**
 * Sending email, as a port.
 *
 * The provider is undecided (`FEAT-NOT-008` `N-3`) and will change. What the
 * application needs is «deliver this», and it should not have to be edited
 * when the answer arrives.
 *
 * Failing is allowed to throw: the caller is a message handler, so a failure
 * is retried and eventually parked in the failure queue, which is exactly
 * what `RN-6` asks for. A user blocked without their email is a lost sign-up.
 */
interface Mailer
{
    public function send(EmailMessage $message): void;
}
