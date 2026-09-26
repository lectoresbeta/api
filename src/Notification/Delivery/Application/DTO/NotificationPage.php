<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\DTO;

/**
 * Una página de la bandeja, y dónde sigue.
 *
 * Cada fila viaja con su tipo y su `payload`, **sin frase**: el texto lo
 * compone el cliente (`FEAT-NOT-009`). Eso es lo que permite traducirlo sin
 * desplegar el backend y cambiar la redacción sin migrar nada de lo ya
 * guardado.
 */
final readonly class NotificationPage
{
    /**
     * @param list<NotificationView> $notifications
     */
    public function __construct(
        public array $notifications,
        public ?string $nextCursor,
    ) {
    }
}
