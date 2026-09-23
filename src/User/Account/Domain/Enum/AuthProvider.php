<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Enum;

/**
 * How the person proves who they are.
 *
 * `FACEBOOK` and `LINKEDIN` appear in the design but are deferred
 * (`FEAT-USR-019`): they are not listed here because an enum value with no
 * implementation behind it is an invitation to write code for it.
 */
enum AuthProvider: string
{
    case LOCAL = 'LOCAL';
    case GOOGLE = 'GOOGLE';

    /**
     * Signing up with Google means the address is already verified, so there
     * is no activation email to wait for (`OB-11`).
     */
    public function verifiesEmailOnSignUp(): bool
    {
        return self::GOOGLE === $this;
    }

    public function requiresPassword(): bool
    {
        return self::LOCAL === $this;
    }
}
