<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Application\DTO;

/**
 * The questionnaire in force, as the API shows it.
 *
 * It carries `requiredWords` because that is what the author is really
 * deciding when they fill this in: the price of every correction of this work
 * (`FEAT-WRK-014`).
 */
final readonly class QuestionnaireView
{
    /**
     * @param list<QuestionView> $questions
     */
    public function __construct(
        public string $workId,
        public int $version,
        public int $requiredWords,
        public array $questions,
    ) {
    }
}
