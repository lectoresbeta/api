<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The token does not exist, was already used, or belongs to an account that
 * is gone (`FEAT-USR-020` `RN-5`).
 *
 * **The three cases are deliberately indistinguishable.** Telling them apart
 * would let somebody with a list of tokens learn which ones were real.
 */
final class InvalidActivationToken extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The activation link is not valid.');
    }

    public function errorCode(): string
    {
        return 'INVALID_ACTIVATION_TOKEN';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
