<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Service;

/**
 * Counts the words of a chapter.
 *
 * It matters more than it looks: this number is the reading term of the
 * price (`decision:0006`), so it has to agree with what the reader sees on
 * screen. Words are counted over **plain text**, with no markup and no
 * headings.
 *
 * `C-13` — how exactly to count rich text — is still open. What is settled is
 * where the answer lives: here, in `Work`, and never in `Credits`.
 */
final class WordCounter
{
    /**
     * Takes **plain text**, already derived by the sanitiser. It does not
     * strip markup: if it did, there would be two places deciding what counts
     * as a word, and the day they disagreed the price would depend on which
     * one ran.
     */
    public function count(string $plainText): int
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', $plainText));

        if ('' === $plain) {
            return 0;
        }

        return \count(preg_split('/\s+/u', $plain) ?: []);
    }
}
