<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * El enlace de confirmación ya se usó, caducó, o lo anuló una solicitud
 * posterior (`FEAT-USR-040` `RN-5`, `RN-6`).
 *
 * Los tres responden igual porque para quien está delante significan lo
 * mismo: ese enlace existió y ya no sirve, así que hay que pedir el cambio
 * otra vez.
 */
final class EmailChangeLinkNoLongerValid extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The confirmation link is no longer valid. Ask for the change again.');
    }

    public function errorCode(): string
    {
        return 'EMAIL_CHANGE_LINK_NO_LONGER_VALID';
    }

    public function kind(): FailureKind
    {
        return FailureKind::GONE;
    }
}
