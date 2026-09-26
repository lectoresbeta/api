<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\Query;

final readonly class ListMyConversations
{
    public function __construct(
        public string $memberId,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
