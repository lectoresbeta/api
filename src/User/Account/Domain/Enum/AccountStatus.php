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

    /**
     * A deleted account is **anonymised**: there is nothing left to show, and
     * its profile answers as if it had never existed (`FEAT-USR-014` `RN-7`).
     * What survives is the identifier, which corrections and credit movements
     * still point at.
     */
    public function isDeleted(): bool
    {
        return self::DELETED === $this;
    }

    /**
     * A blocked account does **not** authenticate
     * ([`FEAT-MOD-006`](../../../../../docs/features/moderation/FEAT-MOD-006-sanctions.md),
     * [`decision:0007`](../../../../../docs/decisions/0007-jwt-sessions.md)).
     *
     * An unactivated one does: the onboarding happens before activating
     * (`FEAT-USR-001` `RN-9`), so refusing here would lock everybody out
     * immediately after signing up. What it cannot do is write
     * (`canWrite()`).
     */
    public function canAuthenticate(): bool
    {
        return self::PENDING_ACTIVATION === $this || self::ACTIVE === $this;
    }
}
