<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The link was real and is past its lifetime (`FEAT-USR-020` `RN-4`).
 *
 * This one **is** told apart from an invalid token, and on purpose: the user
 * needs to be offered a new email (`FEAT-USR-021`), and «your link expired»
 * only leaks something to somebody who already held a valid link.
 */
final class ActivationTokenExpired extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The activation link has expired. Request a new one.');
    }

    public function errorCode(): string
    {
        return 'ACTIVATION_TOKEN_EXPIRED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::GONE;
    }
}
