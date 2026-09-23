<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Application\Handler;

use LectoresBeta\User\Account\Application\Contract\ReaderMaturity;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\Service\WorkReadPolicy;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Questionnaire\Application\DTO\QuestionnaireView;
use LectoresBeta\Work\Questionnaire\Application\DTO\QuestionView;
use LectoresBeta\Work\Questionnaire\Application\Query\GetQuestionnaire;
use LectoresBeta\Work\Questionnaire\Domain\Entity\Question;
use LectoresBeta\Work\Questionnaire\Domain\Repository\QuestionnaireRepository;

/**
 * Reading the questionnaire in force (`FEAT-WRK-014`).
 *
 * Who may see it is decided by **the work**, with the same policy that
 * governs reading its text: a questionnaire describes an unpublished work as
 * surely as its chapters do — the questions name characters and plot points.
 * Guarding the text and leaving the questions open would be guarding the
 * front door and leaving the window.
 */
final readonly class GetQuestionnaireHandler
{
    public function __construct(
        private WorkRepository $works,
        private QuestionnaireRepository $questionnaires,
        private WorkReadPolicy $policy,
        private ReaderMaturity $maturity,
    ) {
    }

    public function __invoke(GetQuestionnaire $query): ?QuestionnaireView
    {
        $work = $this->works->ofId(WorkId::fromString($query->workId));
        $reader = AuthorId::fromString($query->readerId);

        if (null === $work || !$this->policy->allows($work, $reader, $this->maturity->isOfAge($query->readerId))) {
            throw WorkNotFound::create();
        }

        $questionnaire = $this->questionnaires->currentOf($work->id());

        if (null === $questionnaire) {
            // A work without a questionnaire yet is a normal state, not an
            // error: it is created empty and configured afterwards.
            return null;
        }

        $questions = $this->questionnaires->questionsOf($questionnaire->id());

        return new QuestionnaireView(
            $work->id()->value(),
            $questionnaire->version(),
            array_sum(array_map(static fn (Question $q): int => $q->minWords() ?? 0, $questions)),
            array_map(
                static fn (Question $q): QuestionView => new QuestionView(
                    $q->id()->value(),
                    $q->position(),
                    $q->statement(),
                    $q->example(),
                    $q->isRequired(),
                    $q->minWords() ?? 0,
                    $q->maxWords(),
                    $q->scope()->value,
                ),
                $questions,
            ),
        );
    }
}
