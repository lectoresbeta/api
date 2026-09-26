<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Command;

final readonly class SetAccessMode
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public string $accessMode,
    ) {
    }
}
