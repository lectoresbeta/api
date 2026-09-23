<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\DTO;

/**
 * What opening or renewing a session hands back.
 *
 * `refreshToken` is the plain, single-use secret; what is stored is its hash.
 * Both values are credentials, so this object has no `__toString` and must
 * never reach a log.
 */
final readonly class Session
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {
    }
}
