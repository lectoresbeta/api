<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Application\Security;

/**
 * A single-use secret and the hash that is stored in its place.
 *
 * The two halves travel together exactly once — when the token is created —
 * and then part ways: `plain` goes into the email and is forgotten, `hash`
 * goes into the database. Whoever can read the table cannot use the link.
 *
 * `plain` is deliberately not stringable and this object has no `__toString`,
 * so it cannot end up in a log line by being interpolated into one.
 */
final readonly class SecureToken
{
    public function __construct(
        public string $plain,
        public string $hash,
    ) {
    }
}
