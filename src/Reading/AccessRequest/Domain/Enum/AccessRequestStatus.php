<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Domain\Enum;

enum AccessRequestStatus: string
{
    case PENDING = 'PENDING';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public function isOpen(): bool
    {
        return self::PENDING === $this;
    }
}
