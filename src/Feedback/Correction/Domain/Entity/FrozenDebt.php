<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Entity;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;

/**
 * Hasta cuándo la deuda de un autor **no retiene**, según lo último que
 * `Credits` dijo (`FEAT-CRD-018` `RN-8b`, `FEAT-MOD-006` `RN-9`).
 *
 * Una proyección, como `CorrectableChapter`: aquí no se decide nada sobre el
 * saldo de nadie, solo se recuerda una fecha que llegó por `CreditDebtFrozen`.
 *
 * **Es una fila y no un evento recordado a medias**, y esa es la razón de que
 * exista. Sin ella, `Feedback` solo sabría que la deuda estaba congelada en el
 * instante en que llegó el hecho, y una corrección entregada tres días después
 * se retendría igualmente — mientras el autor sigue sin poder corregir. La
 * fila responde «¿ahora mismo?» todas las veces que haga falta.
 *
 * **La fila caduca sola.** Cuando pasa `frozenUntil` deja de significar nada,
 * y no hace falta que nadie venga a borrarla. Es lo que permite que el
 * vencimiento natural de una sanción no necesite ni un hecho ni un proceso
 * programado que lo anuncie.
 */
class FrozenDebt
{
    private string $authorId;

    private \DateTimeImmutable $frozenUntil;

    /**
     * Cuándo se levantó la sanción **antes** de ese plazo.
     *
     * Dos fechas en vez de borrar la fila, por la misma razón que en
     * `Credits`: la cola reentrega, y si levantar borrase, la siguiente
     * reentrega de `CreditDebtFrozen` volvería a crearla. Guardando las dos,
     * reaplicar un hecho ya aplicado no cambia nada.
     */
    private ?\DateTimeImmutable $liftedAt = null;

    private \DateTimeImmutable $updatedAt;

    public function __construct(AuthorId $authorId, \DateTimeImmutable $frozenUntil, \DateTimeImmutable $now)
    {
        $this->authorId = $authorId->value();
        $this->frozenUntil = $frozenUntil;
        $this->updatedAt = $now;
    }

    public function authorId(): AuthorId
    {
        return AuthorId::fromString($this->authorId);
    }

    public function isInForceAt(\DateTimeImmutable $moment): bool
    {
        if ($moment >= $this->frozenUntil) {
            return false;
        }

        return null === $this->liftedAt || $moment < $this->liftedAt;
    }

    /**
     * **Nunca acorta.** Dos sanciones solapadas dejan la fecha más lejana,
     * que es la que de verdad describe hasta cuándo esa persona no puede
     * corregir.
     *
     * Responde si la ventana ha cambiado: reaplicar el mismo hecho no alarga
     * el plazo ni revive una sanción que se levantó después.
     */
    public function freezeUntil(\DateTimeImmutable $frozenUntil, \DateTimeImmutable $decidedAt, \DateTimeImmutable $now): bool
    {
        $before = [$this->frozenUntil, $this->liftedAt];

        if ($frozenUntil > $this->frozenUntil) {
            $this->frozenUntil = $frozenUntil;
        }

        if (null !== $this->liftedAt && $decidedAt > $this->liftedAt) {
            $this->liftedAt = null;
        }

        if ($before === [$this->frozenUntil, $this->liftedAt]) {
            return false;
        }

        $this->updatedAt = $now;

        return true;
    }

    /**
     * Se levantó la sanción antes de su plazo. Responde si **estaba** en
     * vigor, que es lo único que cambia algo.
     */
    public function liftAt(\DateTimeImmutable $decidedAt, \DateTimeImmutable $now): bool
    {
        if (!$this->isInForceAt($now)) {
            return false;
        }

        if (null !== $this->liftedAt && $this->liftedAt <= $decidedAt) {
            return false;
        }

        $this->liftedAt = $decidedAt;
        $this->updatedAt = $now;

        return true;
    }
}
