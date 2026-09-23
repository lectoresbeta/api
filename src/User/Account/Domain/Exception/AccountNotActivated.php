<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The account exists and has not been activated (`FEAT-USR-025` `RN-3`).
 *
 * It carries its own code rather than reusing a generic `FORBIDDEN` because
 * the interface has to tell the two apart: this one is fixed by following a
 * link in an email, and the screen can offer to send it again
 * (`FEAT-USR-021`). «No tienes permiso» would leave the person with nothing
 * to do.
 */
final class AccountNotActivated extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('Activate your account to be able to do this.');
    }

    public function errorCode(): string
    {
        return 'ACCOUNT_NOT_ACTIVATED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::FORBIDDEN;
    }
}
