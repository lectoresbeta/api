<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Application\Command;

final readonly class ProposeWritingBuddy
{
    public function __construct(
        public string $proposerId,
        public string $partnerId,
    ) {
    }
}
