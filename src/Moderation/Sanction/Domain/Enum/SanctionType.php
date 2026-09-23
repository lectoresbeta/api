<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Domain\Enum;

/**
 * The four families of sanction (`MOD-1`).
 *
 * `PARTIAL_SUSPENSION` is read-only: the person can come in but cannot
 * publish or comment (`MOD-27`). `EXPULSION` moves the account to `BLOCKED`
 * and **does not anonymise it** (`MOD-26`) — erasing the data of somebody
 * expelled would also erase the record of why.
 */
enum SanctionType: string
{
    case WARNING = 'WARNING';
    case PARTIAL_SUSPENSION = 'PARTIAL_SUSPENSION';
    case FULL_SUSPENSION = 'FULL_SUSPENSION';
    case EXPULSION = 'EXPULSION';

    public function isTemporary(): bool
    {
        return self::PARTIAL_SUSPENSION === $this;
    }

    public function allowsReading(): bool
    {
        return self::WARNING === $this || self::PARTIAL_SUSPENSION === $this;
    }
}
