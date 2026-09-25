<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\DTO;

/**
 * Un apunte del historial (`FEAT-CRD-008`).
 *
 * `balanceAfter` no es adorno: es lo que permite seguir la cuenta hacia atrás
 * sin sumar a mano, y lo que convierte la invariante del contexto —el saldo
 * es la suma de sus movimientos— en algo comprobable por quien lo mira.
 *
 * **El objeto va por referencia, nunca por nombre.** Este contexto no conoce
 * el título de ninguna obra y no va a conocerlo: pedírselo a `Work` sería la
 * dependencia que [`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)
 * prohíbe. Quien pinte la pantalla resuelve los títulos donde viven.
 */
final readonly class CreditMovement
{
    public function __construct(
        public string $movementId,
        public int $amount,
        public string $reason,
        public int $balanceAfter,
        public string $occurredAt,
        public ?string $correctionId,
        public ?string $chapterId,
        public ?string $workId,
        public ?string $claimId,
        /** Un precio reconstruido es una reparación, y se dice (`FEAT-CRD-006` `RN-7`). */
        public bool $reconstructedPrice,
    ) {
    }
}
