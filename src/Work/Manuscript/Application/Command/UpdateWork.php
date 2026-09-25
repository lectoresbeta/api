<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Command;

/**
 * Editar los datos de la obra (`FEAT-WRK-005`).
 *
 * Campos sueltos y opcionales: quien no los envía no los cambia. Por eso
 * `synopsis` lleva un indicador aparte — enviar `null` es **borrarla**, y no
 * enviarla es no tocarla, que son dos intenciones distintas que un `null` a
 * secas no distingue.
 */
final readonly class UpdateWork
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public ?string $title,
        public ?string $synopsis,
        public bool $synopsisWasSent,
    ) {
    }
}
