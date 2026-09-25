<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\Query;

final readonly class ListConversationMessages
{
    public function __construct(
        public string $memberId,
        public string $conversationId,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
