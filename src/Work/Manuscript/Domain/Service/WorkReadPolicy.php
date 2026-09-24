<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Service;

use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Enum\BetaReaderAccessMode;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;

/**
 * Who may read a work (`FEAT-WRK-004`).
 *
 * One place, and a pure function of what it is given, so the rule can be read
 * in full and tested without a database. It is the most dangerous rule in the
 * backend: this platform custodies unpublished writing, and a leak here is
 * the worst failure the product has.
 *
 * Five gates, and they are deliberately ordered from the most secret
 * outwards:
 *
 * 1. **the author always reads their own work**, in any state;
 * 2. a draft **does not exist** for anybody else;
 * 3. a blocked work disappears for everybody but its author;
 * 4. `ADULTS_ONLY` needs somebody of age, and «has not said» counts as «no»;
 * 5. a beta reader reads what they were given access to, whatever the mode.
 *
 * The fifth is what makes `ON_REQUEST` and `PRIVATE` mean anything: until it
 * existed, granting somebody access let them correct a work they could not
 * read. Whether they hold that access is decided in `Reading` and arrives
 * here as **a boolean**, through its published contract — this rule stays a
 * pure function of what it is given, testable without a database.
 */
final class WorkReadPolicy
{
    public function allows(Work $work, AuthorId $reader, bool $readerIsOfAge, bool $isBetaReader): bool
    {
        if ($work->authorId()->equals($reader)) {
            return true;
        }

        if (!$work->status()->isReadableByOthers() || $work->isBlocked()) {
            return false;
        }

        if ($work->isAdultsOnly() && !$readerIsOfAge) {
            return false;
        }

        return $isBetaReader || BetaReaderAccessMode::PUBLIC === $work->accessMode();
    }
}
