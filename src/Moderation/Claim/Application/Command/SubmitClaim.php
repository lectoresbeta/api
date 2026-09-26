<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Application\Command;

/**
 * Presentar una reclamación (`FEAT-MOD-001`).
 *
 * `description` es opcional en la forma y es **donde está la información
 * real**: el motivo del catálogo ordena el trabajo del moderador, el texto
 * libre es lo que le permite decidir.
 */
final readonly class SubmitClaim
{
    public function __construct(
        public string $reporterId,
        public string $targetType,
        public string $targetId,
        public string $reason,
        public ?string $description,
        /**
         * Quién la registra, cuando no la presenta el propio reclamante
         * (`FEAT-MOD-005` `RN-12`).
         *
         * Nulo es el caso normal. Con valor, la reclamación **queda marcada
         * como registrada en nombre de otro** y no se disfraza de ordinaria.
         */
        public ?string $registeredById = null,
    ) {
    }
}
