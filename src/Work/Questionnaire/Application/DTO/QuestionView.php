<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Application\DTO;

final readonly class QuestionView
{
    public function __construct(
        public string $questionId,
        public int $position,
        public string $statement,
        public ?string $example,
        public bool $required,
        public int $minWords,
        public ?int $maxWords,
        public string $scope,
    ) {
    }
}
