<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese enlace de confirmación no existe (`FEAT-USR-040` `RN-5`).
 *
 * Distinto de uno gastado o caducado, que responden `410`: aquellos existieron
 * y ya no sirven, y eso le dice a quien está delante que pida el cambio otra
 * vez. Este no existió nunca.
 */
final class InvalidEmailChangeToken extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The confirmation link is not valid.');
    }

    public function errorCode(): string
    {
        return 'INVALID_EMAIL_CHANGE_TOKEN';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
