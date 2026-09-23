<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Questionnaire\Application\Command\ReplaceQuestionnaire;
use LectoresBeta\Work\Questionnaire\Application\DTO\QuestionInput;
use LectoresBeta\Work\Questionnaire\Domain\Entity\Question;
use LectoresBeta\Work\Questionnaire\Domain\Entity\Questionnaire;
use LectoresBeta\Work\Questionnaire\Domain\Enum\QuestionScope;
use LectoresBeta\Work\Questionnaire\Domain\Event\QuestionnaireUpdated;
use LectoresBeta\Work\Questionnaire\Domain\Exception\InvalidQuestionnaire;
use LectoresBeta\Work\Questionnaire\Domain\Exception\StaleQuestionnaireVersion;
use LectoresBeta\Work\Questionnaire\Domain\Repository\QuestionnaireRepository;
use LectoresBeta\Work\Questionnaire\Domain\Service\QuestionnairePolicy;
use LectoresBeta\Work\Questionnaire\Domain\ValueObject\QuestionId;
use LectoresBeta\Work\Questionnaire\Domain\ValueObject\QuestionnaireId;

/**
 * Defining what the author will ask of whoever corrects their work
 * (`FEAT-WRK-014`).
 *
 * This is not a form builder. **It is the instrument with which an author
 * sets the price of their own correction**: the sum of the minimum word
 * counts is the writing term of the price, and the reward the reader earns
 * (`decision:0006`, rule 1). That is why the limits here are business rules
 * and not input validation.
 *
 * Saving **creates a new version and keeps the old one** (`RN-4`). Without
 * that, a correction already delivered would be orphaned: answers with no
 * questions, and an author reading a text with no idea what it replies to.
 * It also means editing never disturbs a correction in progress (`RN-5`,
 * `RN-7`).
 */
final readonly class ReplaceQuestionnaireHandler
{
    public function __construct(
        private WorkRepository $works,
        private QuestionnaireRepository $questionnaires,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ReplaceQuestionnaire $command): int
    {
        $work = $this->works->ofId(WorkId::fromString($command->workId));

        if (null === $work || !$work->authorId()->equals(AuthorId::fromString($command->authorId))) {
            throw WorkNotFound::create();
        }

        $current = $this->questionnaires->currentOf($work->id());

        if (null !== $command->expectedVersion && $command->expectedVersion !== ($current?->version() ?? 0)) {
            throw StaleQuestionnaireVersion::expected($current?->version() ?? 0);
        }

        $questions = self::validated($command->questions);
        $now = $this->clock->now();
        $version = ($current?->version() ?? 0) + 1;

        $questionnaire = new Questionnaire(QuestionnaireId::generate(), $work->id(), $version, $now);

        $this->session->execute(function () use ($questionnaire, $questions): void {
            $this->questionnaires->save($questionnaire);

            foreach ($questions as $position => $input) {
                $question = new Question(
                    QuestionId::generate(),
                    $questionnaire->id(),
                    $position + 1,
                    $input->statement,
                    QuestionScope::from($input->scope),
                );
                $question->configure($input->example, $input->required, $input->minWords, $input->maxWords);

                $this->questionnaires->addQuestion($question);
            }
        });

        $this->events->publish(new QuestionnaireUpdated(
            EventId::generate(),
            $work->id(),
            $version,
            \count($questions),
            self::requiredWords($questions),
            self::requiredWords($questions, QuestionScope::EVERY_CHAPTER),
            $now,
        ));

        return $version;
    }

    /**
     * @param list<QuestionInput> $questions
     *
     * @return list<QuestionInput>
     */
    private static function validated(array $questions): array
    {
        if (\count($questions) < QuestionnairePolicy::MIN_QUESTIONS) {
            throw InvalidQuestionnaire::withoutQuestions();
        }

        if (\count($questions) > QuestionnairePolicy::MAX_QUESTIONS) {
            throw InvalidQuestionnaire::withTooManyQuestions();
        }

        $appliesToEveryChapter = false;

        foreach ($questions as $index => $question) {
            $position = $index + 1;

            if ('' === trim($question->statement)) {
                throw InvalidQuestionnaire::withEmptyStatement($position);
            }

            if (null === QuestionScope::tryFrom($question->scope)) {
                throw InvalidQuestionnaire::withEmptyStatement($position);
            }

            if (null === $question->minWords || $question->minWords < 1) {
                throw InvalidQuestionnaire::withoutMinimumWords($position);
            }

            if (null !== $question->maxWords && $question->minWords > $question->maxWords) {
                throw InvalidQuestionnaire::withInvertedWordRange($position);
            }

            $appliesToEveryChapter = $appliesToEveryChapter || QuestionScope::EVERY_CHAPTER->value === $question->scope;
        }

        if (!$appliesToEveryChapter) {
            throw InvalidQuestionnaire::withNothingForEveryChapter();
        }

        $requiredWords = self::requiredWords($questions);

        if ($requiredWords > QuestionnairePolicy::MAX_REQUIRED_WORDS) {
            throw InvalidQuestionnaire::demandingTooManyWords($requiredWords);
        }

        return $questions;
    }

    /**
     * How many words the questionnaire demands.
     *
     * Exact, with no floor to apply, because **every question must declare
     * its minimum** (`C-14`). That is what keeps this number unambiguous
     * across the boundary: `Credits` receives a total it can use directly,
     * instead of a total plus a count of questions it would have to guess a
     * value for.
     *
     * The floor of `ChapterPricing` stays where it is, guarding inputs that
     * do not come from a questionnaire.
     *
     * @param list<QuestionInput> $questions
     */
    private static function requiredWords(array $questions, ?QuestionScope $only = null): int
    {
        $total = 0;

        foreach ($questions as $question) {
            if (null !== $only && $question->scope !== $only->value) {
                continue;
            }

            $total += $question->minWords ?? 0;
        }

        return $total;
    }
}
