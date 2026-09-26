<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\Query;

final readonly class GetTourState
{
    public function __construct(
        public string $userId,
        public ?string $tourId = null,
    ) {
    }
}
