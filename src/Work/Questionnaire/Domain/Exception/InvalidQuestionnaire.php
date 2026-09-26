<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\Work\Questionnaire\Domain\Service\QuestionnairePolicy;

/**
 * A questionnaire that cannot exist (`FEAT-WRK-014`).
 *
 * One class with several named constructors, each carrying **its own code**.
 * The alternative — five classes that differ only in a string — would say
 * nothing extra and would be five files to find. What the client needs is to
 * tell the cases apart, and the code does that.
 */
final class InvalidQuestionnaire extends \DomainException implements BusinessFailure
{
    /**
     * Not called `$code`: `Exception` already has one, and PHP will not let a
     * subclass redeclare it as readonly.
     */
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function withoutQuestions(): self
    {
        return new self(
            'QUESTIONNAIRE_WITHOUT_QUESTIONS',
            'A questionnaire needs at least one question. A single free-text field is a questionnaire of one.',
        );
    }

    public static function withTooManyQuestions(): self
    {
        return new self(
            'TOO_MANY_QUESTIONS',
            \sprintf('A questionnaire cannot have more than %d questions.', QuestionnairePolicy::MAX_QUESTIONS),
        );
    }

    /**
     * Past the cap the price stops growing, so every extra word demanded is
     * work the reader would not be paid for.
     */
    public static function demandingTooManyWords(int $requiredWords): self
    {
        return new self(
            'TOO_MANY_REQUIRED_WORDS',
            \sprintf(
                'The questionnaire demands %d words; the maximum is %d, which is where the price stops growing.',
                $requiredWords,
                QuestionnairePolicy::MAX_REQUIRED_WORDS,
            ),
        );
    }

    public static function withInvertedWordRange(int $position): self
    {
        return new self(
            'INVALID_WORD_RANGE',
            \sprintf('Question %d asks for a minimum longer than its maximum.', $position),
        );
    }

    /**
     * Every chapter is corrected against the questionnaire, so one made
     * entirely of last-chapter questions would leave every other chapter with
     * nothing to answer — and the author paying for it.
     */
    public static function withNothingForEveryChapter(): self
    {
        return new self(
            'QUESTIONNAIRE_ONLY_FOR_LAST_CHAPTER',
            'At least one question must apply to every chapter, or the other chapters have nothing to answer.',
        );
    }

    /**
     * Declaring the minimum is mandatory (`C-14`, resuelta).
     *
     * It is the price: the sum of the minimums is the writing term of what a
     * correction costs and what its author pays. A question with no minimum
     * would be asking for work without saying how much, and `Credits` would
     * have to guess with a floor — a number the author never sees and cannot
     * reason about.
     */
    public static function withoutMinimumWords(int $position): self
    {
        return new self(
            'MISSING_MINIMUM_WORDS',
            \sprintf('Question %d does not say how many words it asks for, and that number is the price.', $position),
        );
    }

    public static function withEmptyStatement(int $position): self
    {
        return new self(
            'EMPTY_QUESTION',
            \sprintf('Question %d has no text.', $position),
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
