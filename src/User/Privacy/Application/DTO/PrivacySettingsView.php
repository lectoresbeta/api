<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\DTO;

/**
 * Los ajustes tal y como los ve su titular (`FEAT-USR-038`).
 *
 * **Solo el suyo.** No hay forma de consultar los ajustes de otra persona:
 * saber que alguien tiene el perfil restringido ya es información sobre esa
 * persona. Su efecto se ve en las respuestas de los demás endpoints, no
 * preguntando por él.
 *
 * `activityVisible` no está, y no es un olvido: la etiqueta no dice qué es
 * «actividad» (`S-16`), y ofrecer un interruptor que nada lee sería peor que
 * no ofrecerlo.
 */
final readonly class PrivacySettingsView
{
    public function __construct(
        public string $profileVisibility,
        public string $commentPermission,
        public string $messagePermission,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
