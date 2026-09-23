<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The account is blocked, and the person **is told** (`FEAT-USR-004` `RN-5`).
 *
 * Only somebody who already got the password right reaches this, so it
 * reveals nothing they did not know. Staying silent would leave a sanctioned
 * person convinced they had forgotten their password.
 */
final class AccountBlocked extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('This account is blocked.');
    }

    public function errorCode(): string
    {
        return 'ACCOUNT_BLOCKED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::FORBIDDEN;
    }
}
