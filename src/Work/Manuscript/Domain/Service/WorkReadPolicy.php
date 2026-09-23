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
 * Four gates, and they are deliberately ordered from the most secret outwards:
 *
 * 1. **the author always reads their own work**, in any state;
 * 2. a draft **does not exist** for anybody else;
 * 3. a blocked work disappears for everybody but its author;
 * 4. `ADULTS_ONLY` needs somebody of age, and «has not said» counts as «no».
 *
 * What it does **not** decide is the restricted access modes: `ON_REQUEST`
 * and `PRIVATE` depend on an access granted in `Reading`, a context that does
 * not exist yet. Until it does, the answer is no — the safe side, and the one
 * that will not have to be changed when it arrives.
 */
final class WorkReadPolicy
{
    public function allows(Work $work, AuthorId $reader, bool $readerIsOfAge): bool
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

        return BetaReaderAccessMode::PUBLIC === $work->accessMode();
    }
}
