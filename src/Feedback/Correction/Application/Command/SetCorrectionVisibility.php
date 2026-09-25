<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Command;

final readonly class SetCorrectionVisibility
{
    public function __construct(
        public string $ownerId,
        public string $correctionId,
        public bool $hidden,
    ) {
    }
}
