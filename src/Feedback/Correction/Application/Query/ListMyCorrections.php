<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Query;

final readonly class ListMyCorrections
{
    public function __construct(
        public string $readerId,
        public ?string $workId = null,
        public ?string $status = null,
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
