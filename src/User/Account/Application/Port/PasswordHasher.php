<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Port;

use LectoresBeta\User\Account\Domain\ValueObject\HashedPassword;

/**
 * Hashing a password, as a port.
 *
 * The algorithm and its cost are infrastructure and will change — that is the
 * whole point of a password hash. What the application needs is «turn this
 * into something storable», and it should not have to be edited the day the
 * cost factor goes up.
 */
interface PasswordHasher
{
    public function hash(string $plain): HashedPassword;

    public function matches(string $plain, HashedPassword $hashed): bool;
}
