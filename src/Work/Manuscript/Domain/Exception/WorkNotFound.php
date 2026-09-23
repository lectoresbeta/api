<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The work does not exist, **or it is not yours**.
 *
 * One answer for both, because unpublished writing is what this platform
 * exists to protect and a `403` would confirm to a stranger that a given work
 * is real (`docs/api/conventions/errors.md`).
 */
final class WorkNotFound extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That work does not exist.');
    }

    public function errorCode(): string
    {
        return 'WORK_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
