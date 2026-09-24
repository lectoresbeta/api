<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Contract;

/**
 * What another context needs to know about a work before anybody can ask to
 * read it or be offered it (`FEAT-RDG-002`).
 *
 * It hands over **facts, not a verdict**. Whether this particular person may
 * request access is `Reading`'s decision; what `Work` answers is whose the
 * work is, how it is open and whether it exists for anybody but its author.
 *
 * `visibleToOthers` folds two of `WorkReadPolicy`'s gates — a draft does not
 * exist for anybody else, and a blocked work disappears for everybody but its
 * author — because both have the same answer here and telling them apart
 * outside would mean explaining which one applied.
 *
 * `adultsOnly` travels as a fact and not as a gate, the same way
 * `CorrectionBrief` carries it: who is of age belongs to `User`, and the
 * caller already asks it.
 *
 * Note what it does not carry: not a word of the work's text, and nothing
 * about who already has access — that question is `Reading`'s own, and
 * answering it here would be this contract calling that one
 * ([`decision:0015`](../../../../../docs/decisions/0015-work-and-reading-ask-each-other.md)).
 */
final readonly class WorkAccessBrief
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public string $title,
        public string $accessMode,
        public bool $adultsOnly,
        public bool $visibleToOthers,
    ) {
    }
}
