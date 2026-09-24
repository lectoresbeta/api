<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Command;

final readonly class SetWorkGenres
{
    /**
     * @param list<string> $genres
     */
    public function __construct(
        public string $workId,
        public string $authorId,
        public array $genres,
    ) {
    }
}
