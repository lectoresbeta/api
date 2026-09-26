<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Exception;

/**
 * A value that cannot exist in the domain.
 *
 * It is not a validation error of the HTTP boundary: by the time a value
 * object is being built, the transport-level checks already ran. This is the
 * model refusing to hold something meaningless.
 */
final class InvalidValue extends \InvalidArgumentException implements BusinessFailure
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'INVALID_VALUE';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
