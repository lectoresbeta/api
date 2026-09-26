<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Command;

final readonly class SubmitCorrection
{
    /**
     * @param array<string, string> $answers text by question id
     */
    public function __construct(
        public string $chapterId,
        public string $readerId,
        public array $answers,
    ) {
    }
}
