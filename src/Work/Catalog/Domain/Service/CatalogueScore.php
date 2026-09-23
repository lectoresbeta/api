<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalog\Domain\Service;

/**
 * How the catalogue orders chapters (`decision:0008`).
 *
 *     score = capacity × neglect × freshness
 *
 *     capacity  = min(author balance ÷ chapter price, 10)
 *     neglect   = 1 ÷ (1 + corrections received)
 *     freshness = 1 ÷ (1 + weeks open)^0.5
 *
 * Not popularity, on purpose. Lectores Beta does not exist so that works get
 * read; it exists so that they get corrected. Ordering by popularity would
 * pile every correction onto the same few texts, and a beta-reader site where
 * most texts are never corrected has failed however much traffic it has.
 *
 * The property that makes it work without supervision: **showing a work
 * spends what put it there**. Every correction it receives costs the author
 * credits and raises its correction count, so both of the first two factors
 * fall at once and the work drifts down on its own.
 *
 * The same formula is duplicated in SQL by the repository, which is where
 * ordering actually happens. This class is the readable definition and what
 * the tests check.
 */
final class CatalogueScore
{
    /** Stops an author with a large balance from monopolising the catalogue. */
    public const MAX_CAPACITY = 10.0;

    public function of(
        int $authorBalance,
        int $chapterPrice,
        int $correctionsReceived,
        float $weeksOpen,
    ): float {
        if ($chapterPrice <= 0) {
            throw new \InvalidArgumentException('A chapter price is always at least two credits.');
        }

        $capacity = min($authorBalance / $chapterPrice, self::MAX_CAPACITY);
        $neglect = 1 / (1 + max(0, $correctionsReceived));
        $freshness = 1 / (1 + max(0.0, $weeksOpen)) ** 0.5;

        return max(0.0, $capacity) * $neglect * $freshness;
    }
}
