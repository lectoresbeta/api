<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Service;

/**
 * Counts the words of an answer.
 *
 * `Work` has its own counter for the text of a chapter, and this is
 * deliberately a second one rather than something shared: they count
 * different things for different reasons, and a shared counter would be a
 * shared model between two contexts.
 *
 * What they must agree on is the **definition** — runs of non-whitespace —
 * because the author declares a minimum in words and the reader is measured
 * against it. The day they disagreed, somebody would be refused for writing
 * exactly what was asked.
 */
final class AnswerWordCounter
{
    public function count(string $text): int
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ('' === $plain) {
            return 0;
        }

        return \count(preg_split('/\s+/u', $plain) ?: []);
    }
}
