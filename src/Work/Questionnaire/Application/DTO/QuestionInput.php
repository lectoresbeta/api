<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Application\DTO;

/**
 * One question as it arrives from the editor, before anything validates it.
 */
final readonly class QuestionInput
{
    public function __construct(
        public string $statement,
        public ?string $example = null,
        public bool $required = true,
        public ?int $minWords = null,
        public ?int $maxWords = null,
        public string $scope = 'EVERY_CHAPTER',
    ) {
    }
}
