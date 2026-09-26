<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Application\Command;

use LectoresBeta\Work\Questionnaire\Application\DTO\QuestionInput;

/**
 * Replacing the questionnaire of a work (`FEAT-WRK-014`).
 *
 * Replacing and not patching: the author edits a form as a whole, and a
 * partial update would need a way to express «delete question 3 and move 4
 * up» that nobody would enjoy writing or reading.
 *
 * `expectedVersion` is the version the author had on screen. It is how two
 * tabs stop silently overwriting each other.
 */
final readonly class ReplaceQuestionnaire
{
    /**
     * @param list<QuestionInput> $questions
     */
    public function __construct(
        public string $workId,
        public string $authorId,
        public array $questions,
        public ?int $expectedVersion = null,
    ) {
    }
}
