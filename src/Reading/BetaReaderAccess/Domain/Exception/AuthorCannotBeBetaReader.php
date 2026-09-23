<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Domain\Exception;

/**
 * `RN-1`: the author of a work is not a beta reader of it. Allowing it would
 * let somebody correct their own work and pay themselves.
 */
final class AuthorCannotBeBetaReader extends \DomainException
{
    public static function ofWork(string $workId): self
    {
        return new self(\sprintf('The author of the work %s cannot be its beta reader.', $workId));
    }
}
