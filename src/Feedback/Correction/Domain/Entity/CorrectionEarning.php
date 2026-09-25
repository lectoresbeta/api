<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Entity;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;

/**
 * Lo que se ganó por una corrección, **copiado de un hecho de `Credits`**
 * (`FEAT-FBK-010`).
 *
 * Es una proyección y no un contrato porque `Credits` no publica ninguno, ni
 * siquiera de consulta
 * ([`AGENTS.md`](../../../../../AGENTS.md),
 * [`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)).
 * Preguntarle en caliente sería justo la dependencia que esa decisión
 * prohíbe.
 *
 * Vive en su propia tabla y no en una columna de `correction` a propósito: la
 * corrección es de este contexto y el importe es un eco de otro. Mezclarlos
 * invitaría a creer que `Feedback` sabe de dinero, y de ahí a calcular
 * precios aquí hay un paso.
 *
 * Tiene el precio de toda proyección: **puede ir un instante por detrás**.
 * Una corrección recién entregada aparece sin importe durante unos segundos,
 * y entonces se muestra como pendiente, nunca como cero — un cero es una
 * afirmación falsa.
 */
class CorrectionEarning
{
    private string $correctionId;

    private int $credits;

    private \DateTimeImmutable $recordedAt;

    public function __construct(CorrectionId $correctionId, int $credits, \DateTimeImmutable $now)
    {
        $this->correctionId = $correctionId->value();
        $this->credits = $credits;
        $this->recordedAt = $now;
    }

    public function correctionId(): CorrectionId
    {
        return CorrectionId::fromString($this->correctionId);
    }

    public function credits(): int
    {
        return $this->credits;
    }

    /**
     * **Fija** la cifra, no la acumula, y eso es lo que hace que un hecho
     * reentregado no la rompa: el transporte promete entregar al menos una
     * vez, así que sumar sería sumar dos veces el día que RabbitMQ repita un
     * mensaje.
     *
     * Una reversión ([`FEAT-MOD-002`](../../../../../docs/features/moderation/FEAT-MOD-002-review-claim.md))
     * la deja en cero, que es lo correcto: se revierte lo que se cobró,
     * entero y nunca en parte. Quien mire su lista después de una reclamación
     * estimada no puede seguir viendo un ingreso que ya no tiene.
     */
    public function settleAt(int $credits, \DateTimeImmutable $now): void
    {
        $this->credits = $credits;
        $this->recordedAt = $now;
    }
}
