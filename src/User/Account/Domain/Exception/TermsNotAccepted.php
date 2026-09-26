<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No account is created without accepting both documents, whatever the sign-up
 * method (`FEAT-USR-024` `RN-1`, `RN-7`).
 */
final class TermsNotAccepted extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The terms of use and the privacy policy must both be accepted.');
    }

    public function errorCode(): string
    {
        return 'TERMS_NOT_ACCEPTED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
