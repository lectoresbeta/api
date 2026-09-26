<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Application\DTO;

/**
 * Lo que el cliente necesita para entrar en el hilo que acaba de abrir.
 */
final readonly class SentMessage
{
    public function __construct(
        public string $conversationId,
        public string $messageId,
        public \DateTimeImmutable $sentAt,
    ) {
    }
}
