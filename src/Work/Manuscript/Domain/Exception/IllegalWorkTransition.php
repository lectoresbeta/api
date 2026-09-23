<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;

final class IllegalWorkTransition extends \DomainException
{
    public static function from(WorkStatus $from, WorkStatus $to): self
    {
        return new self(\sprintf('A work cannot go from %s to %s.', $from->value, $to->value));
    }
}
