<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\Command;

final readonly class SendDirectMessage
{
    public function __construct(
        public string $senderId,
        public string $recipientId,
        public ?string $body,
    ) {
    }
}
