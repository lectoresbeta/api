<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\ValueObject;

/**
 * What one question demands of its answer.
 *
 * The author's wording is deliberately absent: this context validates
 * against the **shape** of what was asked, never against its text. The
 * statement belongs to `Work` and is only passed through to the screen.
 */
final readonly class QuestionRequirement
{
    public function __construct(
        public QuestionId $questionId,
        public int $position,
        public bool $required,
        public int $minWords,
        public ?int $maxWords,
    ) {
    }
}
