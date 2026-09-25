<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Application\Query;

final readonly class ListMyWritingBuddies
{
    public function __construct(
        public string $readerId,
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
