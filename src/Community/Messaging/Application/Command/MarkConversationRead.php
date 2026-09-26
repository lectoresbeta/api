<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\Command;

final readonly class MarkConversationRead
{
    public function __construct(
        public string $memberId,
        public string $conversationId,
    ) {
    }
}
