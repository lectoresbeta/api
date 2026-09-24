<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\Service;

use LectoresBeta\Reading\AccessRequest\Domain\Exception\AccessRequestNotFound;
use LectoresBeta\User\Account\Application\Contract\ReaderMaturity;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBrief;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * The work, as seen by somebody who wants in (`FEAT-RDG-002` `RN-1`).
 *
 * Four situations, **one answer**: it does not exist, it is a stranger's
 * draft, it is blocked by a claim, or it is adults-only and nobody has said
 * this person is of age. Telling them apart would be telling a stranger
 * something about writing that is not published yet, and that is the asset
 * this platform exists to protect.
 *
 * The author is the exception, and it has to be: they are looking at their
 * own draft. They get the brief and are refused later, by name, for the
 * reason that actually applies.
 *
 * Two contracts and no tables: `Work` says what the work is, `User` says
 * whether the person is of age, and neither answer is copied into this
 * context ([`decision:0015`](../../../../../docs/decisions/0015-work-and-reading-ask-each-other.md)).
 */
final readonly class VisibleWork
{
    public function __construct(
        private WorkAccessBriefs $works,
        private ReaderMaturity $maturity,
    ) {
    }

    public function to(string $workId, string $readerId): WorkAccessBrief
    {
        $work = $this->works->ofWork($workId);

        if (null === $work) {
            throw AccessRequestNotFound::work();
        }

        if ($work->authorId === $readerId) {
            return $work;
        }

        if (!$work->visibleToOthers) {
            throw AccessRequestNotFound::work();
        }

        if ($work->adultsOnly && !$this->maturity->isOfAge($readerId)) {
            throw AccessRequestNotFound::work();
        }

        return $work;
    }
}
