<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\DTO;

final readonly class PanelQuestionView
{
    public function __construct(
        public string $questionId,
        public int $position,
        public string $statement,
        public ?string $example,
        public bool $required,
        public int $minWords,
        public ?int $maxWords,
        public string $answer,
    ) {
    }
}
