<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\DTO;

final readonly class MessageRow
{
    public function __construct(
        public string $messageId,
        public string $senderId,
        public bool $mine,
        public string $body,
        public \DateTimeImmutable $sentAt,
        public bool $read,
    ) {
    }
}
