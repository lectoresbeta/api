<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Query;

final readonly class GetCorrectedChapterText
{
    public function __construct(
        public string $correctionId,
        public string $readerId,
    ) {
    }
}
