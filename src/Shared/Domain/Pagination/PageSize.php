<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Pagination;

/**
 * How many items a cursor page carries
 * ([`paginación`](../../../../docs/api/conventions/pagination.md)).
 *
 * A value out of range is **clamped, not refused**, which is the opposite of
 * how this project treats an unknown filter. The difference is what the
 * caller loses: a bad filter silently answers a different question, while
 * `limit=5000` answers the same question with fewer items and a next cursor.
 */
final class PageSize
{
    public const DEFAULT = 20;
    public const MAX = 100;

    /**
     * @return int<1, 100>
     */
    public static function of(?int $requested): int
    {
        if (null === $requested || $requested < 1) {
            return self::DEFAULT;
        }

        return min($requested, self::MAX);
    }
}
