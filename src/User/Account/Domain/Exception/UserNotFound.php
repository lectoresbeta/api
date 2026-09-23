<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No such account.
 *
 * The message is generic because a `BusinessFailure` message **is shown to
 * the caller**: it becomes the `detail` of the problem response. Naming the
 * identifier here would put it in an HTTP body, and an exception message is
 * the wrong place to keep something for a log — the log gets what the caller
 * asked for anyway.
 */
final class UserNotFound extends \DomainException implements BusinessFailure
{
    public static function withId(string $userId): self
    {
        return new self('That account does not exist.');
    }

    public static function withUsername(string $username): self
    {
        return new self('That account does not exist.');
    }

    public function errorCode(): string
    {
        return 'USER_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
