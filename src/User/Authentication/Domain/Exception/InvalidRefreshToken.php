<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The refresh token does not exist, has expired, or was already revoked.
 *
 * The three read the same from outside. When the cause is a **revoked token
 * presented again**, the session lifecycle also takes action —
 * `FEAT-USR-004` `RN-11` — but the caller is told nothing extra: whoever is
 * presenting it may well be the thief.
 */
final class InvalidRefreshToken extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The session could not be renewed. Sign in again.');
    }

    public function errorCode(): string
    {
        return 'INVALID_REFRESH_TOKEN';
    }

    public function kind(): FailureKind
    {
        return FailureKind::UNAUTHENTICATED;
    }
}
