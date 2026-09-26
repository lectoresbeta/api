<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The password does not meet the announced policy (`FEAT-USR-001` `RN-3`).
 *
 * Carries **which** requirements failed and never the password itself, which
 * would otherwise reach a log the first time somebody logs the exception.
 */
final class WeakPassword extends \DomainException implements BusinessFailure
{
    /**
     * @param list<string> $unmetRequirements
     */
    private function __construct(public readonly array $unmetRequirements)
    {
        parent::__construct('The password does not meet the required policy.');
    }

    /**
     * @param list<string> $unmetRequirements
     */
    public static function missing(array $unmetRequirements): self
    {
        return new self($unmetRequirements);
    }

    public function errorCode(): string
    {
        return 'WEAK_PASSWORD';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
