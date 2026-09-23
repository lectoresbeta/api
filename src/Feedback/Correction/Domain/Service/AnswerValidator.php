<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Service;

use LectoresBeta\Feedback\Correction\Domain\Exception\IncompleteCorrection;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\QuestionRequirement;

/**
 * Whether a set of answers is worth delivering (`FEAT-FBK-003` `RN-4`,
 * `RN-5`).
 *
 * The minimum length is the **first defence against being paid for «ok, muy
 * bueno»**, and it is the author's own number: they declared it when they
 * set the price of their correction, so the reader is measured against
 * exactly what was bought.
 *
 * It is not a fraud control and should not be mistaken for one — a long
 * answer can be just as empty. That control is
 * [`FEAT-FBK-012`](../../../../../docs/features/feedback/FEAT-FBK-012-correction-fraud-control.md)
 * and does not exist yet.
 *
 * The whole correction is rejected on the first problem rather than
 * collecting them all. That is worth revisiting the day the screen can show
 * several at once; today it would be a list nobody displays.
 */
final class AnswerValidator
{
    public function __construct(private readonly AnswerWordCounter $words)
    {
    }

    /**
     * @param list<QuestionRequirement> $questions
     * @param array<string, string>     $answers   text by question id
     */
    public function validate(array $questions, array $answers): void
    {
        $asked = [];

        foreach ($questions as $question) {
            $asked[$question->questionId->value()] = true;
            $this->check($question, $answers[$question->questionId->value()] ?? '');
        }

        foreach (array_keys($answers) as $questionId) {
            if (!isset($asked[$questionId])) {
                throw IncompleteCorrection::answeringAnUnknownQuestion();
            }
        }
    }

    private function check(QuestionRequirement $question, string $text): void
    {
        $words = $this->words->count($text);

        if (0 === $words) {
            if ($question->required) {
                throw IncompleteCorrection::missingAnswerTo($question->position);
            }

            // An optional question left blank is not a short answer: nobody
            // promised to answer it at all.
            return;
        }

        if ($words < $question->minWords) {
            throw IncompleteCorrection::answerTooShort($question->position, $words, $question->minWords);
        }

        if (null !== $question->maxWords && $words > $question->maxWords) {
            throw IncompleteCorrection::answerTooLong($question->position, $words, $question->maxWords);
        }
    }
}
