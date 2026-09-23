<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Enum;

enum NotificationChannel: string
{
    case IN_APP = 'IN_APP';
    case EMAIL = 'EMAIL';
}
