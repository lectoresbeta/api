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
use LectoresBeta\User\Privacy\Application\Contract\AuthorAudience;
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
 * `Reading` whether this person is a beta reader, and `User` both whether
 * they are old enough and whether the author is taking comments at all.
 *
 * That last one is a **ceiling** (`FEAT-USR-038` `RN-2`): the profile sets
 * the maximum and each work may lower it, never raise it. It is checked
 * before the work's own mode because that is what «ceiling» means — a work
 * published as `PUBLIC` does not get past a profile that is closed.
 */
final readonly class EligibleCorrectionBrief
{
    public function __construct(
        private CorrectionBriefs $briefs,
        private BetaReaderAccessCheck $access,
        private ReaderMaturity $maturity,
        private AuthorAudience $audience,
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

        // El techo del perfil, antes que la modalidad de la obra: una obra
        // `PUBLIC` no pasa por encima de un perfil cerrado. Ni siquiera quien
        // ya tiene acceso concedido — lo que se cerró es la puerta de
        // comentar, no la de entrar.
        if (!$this->audience->acceptsCommentsFrom($brief->authorId, $readerId)) {
            throw CorrectionNotAllowed::becauseTheAuthorTookCommentsDown();
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
