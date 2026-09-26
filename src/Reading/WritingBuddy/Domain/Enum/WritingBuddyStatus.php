<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Domain\Enum;

enum WritingBuddyStatus: string
{
    case PROPOSED = 'PROPOSED';
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
    case ENDED = 'ENDED';

    public function isLive(): bool
    {
        return self::ACCEPTED === $this;
    }
}
