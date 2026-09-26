<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Domain\Enum;

/**
 * Three steps, and only for a partial suspension (`MOD-1`).
 */
enum SuspensionDuration: string
{
    case THREE_DAYS = 'THREE_DAYS';
    case ONE_WEEK = 'ONE_WEEK';
    case ONE_MONTH = 'ONE_MONTH';

    public function endingFrom(\DateTimeImmutable $start): \DateTimeImmutable
    {
        return $start->modify(match ($this) {
            self::THREE_DAYS => '+3 days',
            self::ONE_WEEK => '+1 week',
            self::ONE_MONTH => '+1 month',
        });
    }
}
