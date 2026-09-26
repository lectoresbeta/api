<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\ValueObject;

/**
 * The text of a chapter, in the two forms it is kept in.
 *
 * `html` is what a reader is served; `text` is the plain version derived from
 * it, and it is **the source of truth for the word count**, which is the
 * reading term of the price of every correction
 * ([`FEAT-CRD-016`](../../../../../docs/features/credits/FEAT-CRD-016-effort-based-pricing.md)).
 *
 * Both are stored rather than deriving the text on the fly. Deriving it would
 * make the count depend on whatever the stripping code does today, so an
 * innocent change to it would silently reprice every chapter in the platform.
 *
 * It is built by the sanitiser and never from raw input: the only way to get
 * one is to have gone through the whitelist.
 */
final readonly class ChapterContent
{
    public function __construct(
        public string $html,
        public string $text,
    ) {
    }

    public static function empty(): self
    {
        return new self('', '');
    }
}
