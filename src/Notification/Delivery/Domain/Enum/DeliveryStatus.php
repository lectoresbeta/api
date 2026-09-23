<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Enum;

enum DeliveryStatus: string
{
    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
    /** The recipient's preferences said not to use this channel. */
    case SUPPRESSED = 'SUPPRESSED';
}
