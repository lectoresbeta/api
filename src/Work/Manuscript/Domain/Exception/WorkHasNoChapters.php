<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * A work with no content is not a work (`FEAT-WRK-001` `RN-2`).
 *
 * The rule bites **on publishing**, not on creating: a work is born empty on
 * purpose, because chapters are added one at a time. What cannot happen is
 * putting nothing in front of readers.
 */
final class WorkHasNoChapters extends \DomainException implements BusinessFailure
{
    public static function cannotBePublished(string $workId): self
    {
        return new self('A work with no chapters cannot be published.');
    }

    public function errorCode(): string
    {
        return 'WORK_HAS_NO_CHAPTERS';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
