<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Service;

use LectoresBeta\Feedback\Correction\Domain\Enum\WorkAccessMode;
use LectoresBeta\Feedback\Correction\Domain\Exception\ChapterNotCorrectable;
use LectoresBeta\Feedback\Correction\Domain\Exception\ChapterNotFound;
use LectoresBeta\Feedback\Correction\Domain\Exception\CorrectionNotAllowed;
use LectoresBeta\Feedback\Correction\Domain\Service\CorrectionPolicy;
use LectoresBeta\Reading\BetaReaderAccess\Application\Contract\BetaReaderAccessCheck;
use LectoresBeta\User\Account\Application\Contract\ReaderMaturity;
use LectoresBeta\Work\Chapter\Application\Contract\CorrectionBrief;
use LectoresBeta\Work\Chapter\Application\Contract\CorrectionBriefs;

/**
 * Everything that has to be true before somebody corrects a chapter, asked
 * once and in one place (`FEAT-FBK-003`).
 *
 * It is a single service and not a check repeated in each handler because
 * **the order of the answers is itself a decision**: what does not exist and
 * what is not yours give the same reply, the author is refused before the
 * questionnaire is even considered, and an adults-only work is invisible
 * rather than forbidden to somebody too young.
 *
 * Three contexts answer here, each through its published contract and none
 * through its model: `Work` says what is asked and whether the door is open,
 * `Reading` whether this person is a beta reader, and `User` whether they are
 * old enough.
 */
final readonly class EligibleCorrectionBrief
{
    public function __construct(
        private CorrectionBriefs $briefs,
        private BetaReaderAccessCheck $access,
        private ReaderMaturity $maturity,
        private CorrectionPolicy $policy,
    ) {
    }

    public function for(string $chapterId, string $readerId): CorrectionBrief
    {
        $brief = $this->briefs->ofChapter($chapterId);

        if (null === $brief) {
            throw ChapterNotFound::create();
        }

        // An adults-only work is **not found** for somebody who is not of
        // age, not forbidden: a refusal would confirm what the work is.
        if ($brief->adultsOnly && !$this->maturity->isOfAge($readerId)) {
            throw ChapterNotFound::create();
        }

        if ($this->policy->isTheAuthor($readerId, $brief->authorId)) {
            throw CorrectionNotAllowed::toTheAuthor();
        }

        if (!$this->policy->admits(WorkAccessMode::orStrictest($brief->accessMode), $this->access->hasAccessTo($brief->workId, $readerId))) {
            throw CorrectionNotAllowed::withoutBetaReaderAccess();
        }

        if (!$brief->openForCorrection) {
            throw ChapterNotCorrectable::becauseTheWorkIsClosed();
        }

        if (!$brief->hasQuestions()) {
            throw ChapterNotCorrectable::becauseThereIsNoQuestionnaire();
        }

        return $brief;
    }
}
