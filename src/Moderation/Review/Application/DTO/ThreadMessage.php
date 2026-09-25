<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\DTO;

/**
 * Un mensaje del hilo, como lo ve quien lo lee (`FEAT-MOD-009`).
 *
 * **No lleva identidad de quien escribe**, solo si fue moderación o la propia
 * parte. `RN-4`: la conversación no revela quién es el moderador, y firma
 * como «Moderación».
 *
 * No es cortesía: protege al moderador de represalias, y es lo que permite
 * que la decisión se discuta por su contenido y no por quién la tomó.
 */
final readonly class ThreadMessage
{
    public function __construct(
        public string $messageId,
        public bool $fromModeration,
        public string $body,
        public \DateTimeImmutable $sentAt,
    ) {
    }
}
