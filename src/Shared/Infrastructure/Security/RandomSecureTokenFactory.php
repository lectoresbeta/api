<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Security;

use LectoresBeta\Shared\Application\Security\SecureToken;
use LectoresBeta\Shared\Application\Security\SecureTokenFactory;

/**
 * 256 bits from the system CSPRNG, stored as SHA-256.
 *
 * SHA-256 and not a password hash: this is a 256-bit random value, not a
 * human-chosen secret, so there is nothing to brute-force and the cost of
 * bcrypt would only slow down every lookup. The reason to hash it at all is
 * that a leaked database must not hand out working activation links.
 */
final readonly class RandomSecureTokenFactory implements SecureTokenFactory
{
    private const BYTES = 32;

    public function create(): SecureToken
    {
        $plain = bin2hex(random_bytes(self::BYTES));

        return new SecureToken($plain, $this->hashOf($plain));
    }

    public function hashOf(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
