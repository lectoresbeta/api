<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Application\DTO;

/**
 * Una fila de la cola de asuntos vivos (`FEAT-MOD-006` `MOD-25`).
 *
 * Lleva **cuántos días lleva abierta**, que es el dato por el que se entra a
 * esta pantalla: una suspensión total de hace nueve meses no es lo mismo que
 * una de ayer, y la fecha cruda obliga a quien mira a hacer la resta.
 *
 * No lleva quién la impuso. Un moderador no es un actor social y nombrarlo
 * convertiría una decisión de la plataforma en un asunto entre dos personas
 * — la misma razón por la que no viaja en el aviso al sancionado (`RN-4`).
 */
final readonly class OpenSanction
{
    public function __construct(
        public string $sanctionId,
        public string $userId,
        public string $type,
        public string $reason,
        public string $imposedAt,
        public int $openForDays,
    ) {
    }
}
