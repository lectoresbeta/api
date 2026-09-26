<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Command;

final readonly class MarkNotificationRead
{
    public function __construct(
        public string $userId,
        public string $notificationId,
    ) {
    }
}
