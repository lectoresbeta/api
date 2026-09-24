<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * It is not there, or it must not be revealed to be there (`FEAT-RDG-002`).
 *
 * The two factories answer different questions and say the same thing on
 * purpose. A stranger's draft, a blocked work and a work that never existed
 * are one answer; somebody else's request and a request that never existed
 * are another. **Confirming that either exists is already saying something
 * about it**, and this product custodies unpublished writing.
 */
final class AccessRequestNotFound extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function work(): self
    {
        return new self('WORK_NOT_FOUND', 'That work does not exist.');
    }

    public static function request(): self
    {
        return new self('ACCESS_REQUEST_NOT_FOUND', 'That access request does not exist.');
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
