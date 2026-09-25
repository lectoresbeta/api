<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\DTO;

/**
 * @see MyWork
 */
final readonly class MyWorksPage
{
    /**
     * @param list<MyWork> $works
     */
    public function __construct(
        public array $works,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function totalPages(): int
    {
        return (int) ceil($this->total / max(1, $this->perPage));
    }
}
