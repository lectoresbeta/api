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
    public function count(string $content): int
    {
        $plain = strip_tags($content);
        $plain = html_entity_decode($plain, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $plain = (string) preg_replace('/\s+/u', ' ', $plain);
        $plain = trim($plain);

        if ('' === $plain) {
            return 0;
        }

        return \count(preg_split('/\s+/u', $plain) ?: []);
    }
}
