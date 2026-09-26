<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Service;

use LectoresBeta\User\Account\Domain\Exception\WeakPassword;

/**
 * What counts as an acceptable password (`FEAT-USR-001` `RN-3`).
 *
 * It lives in Domain, not at the HTTP boundary, because it is a rule of the
 * product and not of the transport: the form shows live indicators, but those
 * are a convenience and the server does not trust them (`RN-4`).
 *
 * The plain password passes through as a string and is never stored in an
 * object: what the model keeps is the hash (`HashedPassword`).
 */
final readonly class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    /**
     * Matches the characters the form announces. Widening it later is safe;
     * narrowing it would lock people out of their own accounts.
     */
    private const SPECIAL = '!@#$%^&*';

    /**
     * @throws WeakPassword listing every unmet requirement, so the form can
     *                      show them all at once instead of one per attempt
     */
    public function ensureAcceptable(string $plain): void
    {
        $unmet = [];

        if (mb_strlen($plain) < self::MIN_LENGTH) {
            $unmet[] = 'MIN_LENGTH';
        }

        if (1 !== preg_match('/\p{Lu}/u', $plain)) {
            $unmet[] = 'UPPERCASE';
        }

        if (1 !== preg_match('/\d/', $plain)) {
            $unmet[] = 'DIGIT';
        }

        if (1 !== preg_match('/['.preg_quote(self::SPECIAL, '/').']/', $plain)) {
            $unmet[] = 'SPECIAL_CHARACTER';
        }

        if ([] !== $unmet) {
            throw WeakPassword::missing($unmet);
        }
    }
}
