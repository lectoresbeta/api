<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\DTO;

/**
 * La pantalla de «Configuración › Notificaciones» (`FEAT-USR-039`).
 *
 * Lleva **los tipos disponibles por canal**, no solo los valores, y esa es la
 * decisión que evita una deuda: si el cliente llevase la lista codificada,
 * cada aviso nuevo exigiría desplegar el frontal. Así la pantalla se dibuja
 * con lo que el servidor dice que existe.
 */
final readonly class NotificationPreferences
{
    /**
     * @param list<array{topic: string, channels: array<string, bool>}> $topics
     */
    public function __construct(
        public bool $allMuted,
        public array $topics,
    ) {
    }
}
