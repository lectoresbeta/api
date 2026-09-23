<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The one answer for four different situations (`FEAT-USR-004` `RN-2`):
 * wrong password, no such account, an account with no password because it was
 * created with Google, and a deleted account.
 *
 * They are indistinguishable on purpose. Any difference — a code, a wording,
 * a status — turns the login form into a way of finding out which addresses
 * have an account here.
 */
final class InvalidCredentials extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The email address or the password is not correct.');
    }

    public function errorCode(): string
    {
        return 'INVALID_CREDENTIALS';
    }

    public function kind(): FailureKind
    {
        return FailureKind::UNAUTHENTICATED;
    }
}
