<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Query;

final readonly class ListCatalogue
{
    /**
     * @param list<string> $genres
     */
    public function __construct(
        public string $readerId,
        public array $genres,
        public ?string $status,
        public string $sort,
        public int $page,
        public int $perPage,
    ) {
    }
}
