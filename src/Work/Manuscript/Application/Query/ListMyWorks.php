<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Query;

final readonly class ListMyWorks
{
    public function __construct(
        public string $authorId,
        public ?string $status,
        public ?string $sort,
        public int $page,
        public int $perPage,
    ) {
    }
}
