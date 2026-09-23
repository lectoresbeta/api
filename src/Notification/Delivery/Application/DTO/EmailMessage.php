<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\DTO;

/**
 * An email, as the application describes it: who, what, and both bodies.
 *
 * Plain text alongside HTML is not decoration. A message with only HTML is
 * scored worse by spam filters and is unreadable to anyone whose client shows
 * text — and this is the message without which the account does not work
 * (`FEAT-NOT-008` `N-7`).
 */
final readonly class EmailMessage
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $text,
        public string $html,
    ) {
    }
}
