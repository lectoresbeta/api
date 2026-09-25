<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Query;

/**
 * El muro de quien mira (`FEAT-COM-001`).
 */
final readonly class ListPosts
{
    public function __construct(
        public string $readerId,
        public ?string $cursor,
        public ?int $limit,
    ) {
    }
}
