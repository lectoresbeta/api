<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Command;

final readonly class DiscardCorrectionDraft
{
    public function __construct(
        public string $chapterId,
        public string $readerId,
    ) {
    }
}
