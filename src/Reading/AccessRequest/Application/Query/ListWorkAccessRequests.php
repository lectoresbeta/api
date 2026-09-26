<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\Query;

final readonly class ListWorkAccessRequests
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public ?string $status,
        public ?string $cursor,
        public ?int $limit,
    ) {
    }
}
