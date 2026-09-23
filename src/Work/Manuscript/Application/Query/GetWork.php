<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Query;

final readonly class GetWork
{
    public function __construct(
        public string $workId,
        public string $readerId,
    ) {
    }
}
