<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Application\Contract;

/**
 * Qué puede hacer alguien en moderación, para quien necesite autorizarlo.
 *
 * Un nombre de nivel y nada más: ni la fila, ni quién se lo concedió, ni
 * cuándo. Quien pregunta está decidiendo si deja pasar una petición, no
 * auditando.
 */
final readonly class ModeratorStanding
{
    /**
     * Los dos niveles, escritos **aquí** y no en el enum del dominio. Quien
     * consume el contrato tiene que poder distinguirlos sin asomarse dentro
     * de este contexto, que es justo lo que un contrato evita.
     */
    public const MODERATOR = 'MODERATOR';
    public const ADMIN = 'ADMIN';

    public function __construct(
        public string $userId,
        public string $level,
    ) {
    }
}
