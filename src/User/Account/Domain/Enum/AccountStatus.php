<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Enum;

/**
 * The life of an account.
 *
 * `PENDING_ACTIVATION → ACTIVE → DELETED` is the ordinary path.
 * `BLOCKED` is not part of it: it is imposed from outside, when `Moderation`
 * publishes an expulsion (`FEAT-MOD-006`). A blocked account keeps its data —
 * it is not anonymised — and can read but not write (`MOD-26`, `MOD-27`).
 */
enum AccountStatus: string
{
    case PENDING_ACTIVATION = 'PENDING_ACTIVATION';
    case ACTIVE = 'ACTIVE';
    case BLOCKED = 'BLOCKED';
    case DELETED = 'DELETED';

    /**
     * Only an activated account writes (`decision:0003`).
     */
    public function canWrite(): bool
    {
        return self::ACTIVE === $this;
    }

    public function canAuthenticate(): bool
    {
        return self::DELETED !== $this;
    }
}
