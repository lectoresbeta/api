<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Query;

final readonly class GetCorrectionPanel
{
    public function __construct(
        public string $chapterId,
        public string $readerId,
    ) {
    }
}
