<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * A value that is not one of the three (`FEAT-USR-038` `RN-4b`).
 *
 * **Never read as a default.** A privacy setting is the one place where
 * guessing is worst: interpreting an unknown value as «everyone» opens a door
 * its owner believed closed, and interpreting it as «nobody» closes one they
 * believed open. Refusing says neither.
 */
final class UnknownAudience extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('A privacy setting takes EVERYONE, FOLLOWERS or NOBODY.');
    }

    public function errorCode(): string
    {
        return 'UNKNOWN_AUDIENCE';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
