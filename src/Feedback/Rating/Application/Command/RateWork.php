<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Rating\Application\Command;

final readonly class RateWork
{
    public function __construct(
        public string $readerId,
        public string $workId,
        public ?int $rating,
    ) {
    }
}
