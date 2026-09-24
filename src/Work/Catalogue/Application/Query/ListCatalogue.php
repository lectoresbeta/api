<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Query;

final readonly class ListCatalogue
{
    public function __construct(
        public string $readerId,
        public ?string $status,
        public string $sort,
        public int $page,
        public int $perPage,
    ) {
    }
}
