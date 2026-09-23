<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Enum;

enum DeliveryChannel: string
{
    case IN_APP = 'IN_APP';
    case EMAIL = 'EMAIL';
}
