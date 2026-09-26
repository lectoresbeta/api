<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Domain\Enum;

enum AccessInvitationStatus: string
{
    case PENDING = 'PENDING';
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
    case CANCELLED = 'CANCELLED';

    public function isOpen(): bool
    {
        return self::PENDING === $this;
    }
}
