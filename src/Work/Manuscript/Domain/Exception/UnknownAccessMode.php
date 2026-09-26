<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * A mode that does not exist (`FEAT-WRK-007` `RN-6`).
 *
 * Rejected, never quietly ignored: silently keeping the previous mode would
 * leave an author convinced they had opened their work when they had not, or
 * — worse — that they had closed it.
 */
final class UnknownAccessMode extends \DomainException implements BusinessFailure
{
    public static function named(string $mode): self
    {
        return new self(\sprintf('"%s" is not a beta reader access mode.', $mode));
    }

    public function errorCode(): string
    {
        return 'UNKNOWN_ACCESS_MODE';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
