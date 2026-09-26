<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Domain\Service;

/**
 * The limits of a questionnaire (`FEAT-WRK-014` `RN-2`, `RN-3`).
 *
 * The questionnaire is **the instrument with which an author sets the price
 * of their own correction**, so its limits are not form validation: they are
 * what stops that instrument being used to extract unpaid work.
 */
final class QuestionnairePolicy
{
    public const MIN_QUESTIONS = 1;

    /**
     * Twenty is already a long form to fill in for a single chapter. The
     * number matters less than having one: without a ceiling an author could
     * ask for forty answers and turn a correction into a job.
     */
    public const MAX_QUESTIONS = 20;

    /**
     * The real ceiling, and the one with an argument behind it.
     *
     * The writing term of the price is `ceil(requiredWords / 100)`, and the
     * price is capped at 20 credits (`decision:0006`). Two thousand required
     * words is exactly where that term reaches the cap — **past this point
     * the author pays no more and the reader writes more**, which is unpaid
     * labour with extra steps.
     */
    public const MAX_REQUIRED_WORDS = 2000;
}
