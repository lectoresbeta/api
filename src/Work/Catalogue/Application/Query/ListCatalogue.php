<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Query;

final readonly class ListCatalogue
{
    /**
     * @param list<string> $genres
     * @param list<string> $excludedWarnings
     */
    public function __construct(
        public string $readerId,
        public array $genres,
        public array $excludedWarnings,
        public ?string $status,
        public string $sort,
        public int $page,
        public int $perPage,
    ) {
    }
}
