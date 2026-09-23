<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;

/**
 * A move the life cycle does not allow (`FEAT-WRK-016`).
 *
 * **The server decides which paths exist**, not the client. The message names
 * both ends because both are the caller's own data and knowing them is what
 * lets an interface explain itself.
 */
final class IllegalWorkTransition extends \DomainException implements BusinessFailure
{
    public static function from(WorkStatus $from, WorkStatus $to): self
    {
        return new self(\sprintf('A work cannot go from %s to %s.', $from->value, $to->value));
    }

    public static function unknownStatus(string $status): self
    {
        return new self(\sprintf('"%s" is not a state a work can be in.', $status));
    }

    public static function unpublishingIsNotAvailable(): self
    {
        return new self('A published work cannot go back to being a draft.');
    }

    public function errorCode(): string
    {
        return 'ILLEGAL_WORK_TRANSITION';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
