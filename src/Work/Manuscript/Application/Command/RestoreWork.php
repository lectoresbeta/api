<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Command;

final readonly class RestoreWork
{
    public function __construct(
        public string $workId,
        public string $authorId,
    ) {
    }
}
