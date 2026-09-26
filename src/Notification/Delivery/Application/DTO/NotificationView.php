<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\DTO;

use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;

/**
 * Un aviso tal y como se lee.
 *
 * `readAt` y no solo un booleano: la pantalla puede querer separar lo de hoy
 * de lo de la semana pasada, y una fecha responde las dos preguntas mientras
 * que un booleano solo responde una.
 */
final readonly class NotificationView
{
    /**
     * @param array<string, scalar|null> $payload
     */
    public function __construct(
        public string $notificationId,
        public NotificationKind $kind,
        public array $payload,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $readAt,
    ) {
    }
}
