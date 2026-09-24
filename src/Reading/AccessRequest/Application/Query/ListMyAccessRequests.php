<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\Query;

final readonly class ListMyAccessRequests
{
    public function __construct(
        public string $requesterId,
        public ?string $status,
        public ?string $cursor,
        public ?int $limit,
    ) {
    }
}
