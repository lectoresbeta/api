<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Port;

use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterContent;

/**
 * Turns whatever an editor sent into content this platform will store.
 *
 * **Sanitising happens on write, never on read** (`FEAT-WRK-001`). Nothing is
 * stored that we would not be willing to serve. If it happened on the way
 * out, every new path to that data — an export, an email, an API added later
 * — would become a hole, and each one would have to remember.
 *
 * A port because parsing HTML is infrastructure: the rule about **what** is
 * allowed is in `ContentPolicy`, and a use case should not know which library
 * enforces it.
 */
interface ContentSanitiser
{
    public function sanitise(string $html): ChapterContent;
}
