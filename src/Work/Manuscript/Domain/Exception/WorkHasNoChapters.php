<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

/**
 * A work with no content is not a work (`FEAT-WRK-001` `RN-2`).
 */
final class WorkHasNoChapters extends \DomainException
{
    public static function cannotBePublished(string $workId): self
    {
        return new self(\sprintf('The work %s has no chapters and cannot be published.', $workId));
    }
}
