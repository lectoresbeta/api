<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Command;

final readonly class RateCorrection
{
    public function __construct(
        public string $correctionId,
        public string $authorId,
        public bool $helpful,
    ) {
    }
}
