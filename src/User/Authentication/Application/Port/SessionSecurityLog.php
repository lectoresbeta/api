<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Port;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Where the session's security signals are recorded.
 *
 * A port and not a `LoggerInterface`, for the same reason every other
 * infrastructure capability is one: Application must not know PSR-3 any more
 * than it knows Doctrine. What it needs to say is not «write a warning with
 * this context array» but **«a refresh token was presented twice»** — and a
 * named method says it, while leaving where that ends up, and how loudly, to
 * Infrastructure.
 *
 * It takes identifiers and counts. Never a token, a hash of one, a password
 * or an address: this is the one place in the session flow where the
 * temptation to log «what was presented» is real, and a log with a live
 * credential in it is a credential leak with a timestamp.
 */
interface SessionSecurityLog
{
    /**
     * A refresh token that was already revoked has been presented again, so
     * two parties held it and every session of the user was revoked.
     *
     * The only signal the system has that a token may have been stolen.
     */
    public function refreshTokenReused(UserId $userId, int $revokedSessions): void;
}
