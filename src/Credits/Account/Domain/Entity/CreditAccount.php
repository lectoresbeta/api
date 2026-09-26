<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Entity;

use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

/**
 * Somebody's credit balance (`decision:0006`).
 *
 * **One balance, and it may be negative.** There is no «available balance»,
 * because nothing is ever held back: what the author sees is what they have.
 * A correction is charged on delivery, and if the balance does not cover it
 * the corrector is still paid and the author goes negative (`RN-9`).
 *
 * The stored `balance` is a running total kept for reading. The truth is the
 * sum of the movements, and `FEAT-CRD-012` recomputes it to check they still
 * agree.
 */
class CreditAccount
{
    private string $userId;

    private int $balance = 0;

    /**
     * How many invitation rewards this account has already collected. Capped
     * at ten (`decision:0006`, rule 6) so nobody builds a balance by
     * recruiting instead of correcting.
     */
    private int $invitationRewards = 0;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $updatedAt;

    private ?\DateTimeImmutable $anonymisedAt = null;

    /**
     * Hasta cuándo la deuda **no retiene nada** (`FEAT-CRD-018` `RN-8b`,
     * `FEAT-MOD-006` `RN-9`).
     *
     * Es una fecha y no un interruptor porque la suspensión parcial que la
     * provoca tiene duración, y **al expirar no se publica ningún hecho**:
     * nadie avisa de que un plazo se ha cumplido. Con una fecha, la
     * congelación se apaga sola, sin proceso programado que pueda dejar de
     * ejecutarse y sin que un fallo suyo deje a nadie congelado para siempre.
     *
     * **Lo que se congela es la retención, no la corregibilidad.** La deuda
     * hace dos cosas: retiene el contenido de lo ya entregado y cierra la
     * puerta a recibir más. Congelar la segunda haría que durante la sanción
     * entraran correcciones nuevas y la deuda **creciera**, que es justo lo
     * que `RN-8b` prohíbe en la misma frase. Así que la corregibilidad sigue
     * mirando el saldo de verdad, y esta fecha solo gobierna la retención.
     */
    private ?\DateTimeImmutable $debtFrozenUntil = null;

    /**
     * Y cuándo se levantó la sanción **antes** de ese plazo.
     *
     * Dos fechas en vez de borrar la primera, porque la cola reentrega: si
     * levantar dejara `debtFrozenUntil` a nulo, la siguiente reentrega del
     * `SanctionImposed` original volvería a congelar, y la de `SanctionLifted`
     * a descongelar, para siempre. Guardando las dos, reaplicar un hecho ya
     * aplicado no cambia nada, que es la definición de idempotente.
     */
    private ?\DateTimeImmutable $debtFreezeLiftedAt = null;

    public function __construct(UserId $userId, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function balance(): int
    {
        return $this->balance;
    }

    /**
     * Si hay deuda **de verdad**, congelada o no. Es la contabilidad, y la
     * contabilidad no se congela: lo que se congela son sus efectos.
     */
    public function isInDebt(): bool
    {
        return $this->balance < 0;
    }

    public function isDebtFrozenAt(\DateTimeImmutable $moment): bool
    {
        if (null === $this->debtFrozenUntil || $moment >= $this->debtFrozenUntil) {
            return false;
        }

        return null === $this->debtFreezeLiftedAt || $moment < $this->debtFreezeLiftedAt;
    }

    /**
     * Congelar **nunca acorta**: dos sanciones solapadas dejan la fecha más
     * lejana, que es la que de verdad describe hasta cuándo esa persona no
     * puede corregir.
     *
     * Responde si la ventana ha cambiado de verdad, que es lo que decide si
     * hay algo que anunciar. Reaplicar el mismo hecho responde `false`: no
     * alarga el plazo, y no revive una sanción que se levantó después.
     */
    public function freezeDebtUntil(\DateTimeImmutable $until, \DateTimeImmutable $decidedAt, \DateTimeImmutable $now): bool
    {
        $before = [$this->debtFrozenUntil, $this->debtFreezeLiftedAt];

        if (null === $this->debtFrozenUntil || $until > $this->debtFrozenUntil) {
            $this->debtFrozenUntil = $until;
        }

        if (null !== $this->debtFreezeLiftedAt && $decidedAt > $this->debtFreezeLiftedAt) {
            // Una sanción posterior al levantamiento: es otra, y vuelve a
            // congelar. La reentrega de la que se levantó, no.
            $this->debtFreezeLiftedAt = null;
        }

        if ($before === [$this->debtFrozenUntil, $this->debtFreezeLiftedAt]) {
            return false;
        }

        $this->updatedAt = $now;

        return true;
    }

    /**
     * Levantar la sanción antes de tiempo devuelve la deuda a la vida.
     *
     * Responde si **estaba** congelada, que es lo que decide si hay algo que
     * anunciar: descongelar lo que ya se había descongelado solo no es un
     * hecho.
     */
    public function thawDebt(\DateTimeImmutable $decidedAt, \DateTimeImmutable $now): bool
    {
        if (!$this->isDebtFrozenAt($now)) {
            return false;
        }

        if (null !== $this->debtFreezeLiftedAt && $this->debtFreezeLiftedAt <= $decidedAt) {
            return false;
        }

        $this->debtFreezeLiftedAt = $decidedAt;
        $this->updatedAt = $now;

        return true;
    }

    public function invitationRewards(): int
    {
        return $this->invitationRewards;
    }

    /**
     * Whether a chapter at this price can be opened for correction. With a
     * negative balance no new correction starts, but the holder can still
     * correct — that is how the debt gets paid off (`RN-10`).
     */
    public function canAfford(int $price): bool
    {
        return $this->balance >= $price;
    }

    /**
     * Applies a movement and returns it, so the caller persists exactly what
     * was applied. The balance is never set directly.
     *
     * @param array<string, scalar|null> $metadata
     */
    public function apply(
        CreditTransactionId $transactionId,
        int $amount,
        CreditTransactionReason $reason,
        \DateTimeImmutable $now,
        ?string $eventId = null,
        array $metadata = [],
    ): CreditTransaction {
        $this->balance += $amount;
        $this->updatedAt = $now;

        if (CreditTransactionReason::INVITATION_REWARD === $reason) {
            ++$this->invitationRewards;
        }

        return new CreditTransaction(
            $transactionId,
            $this->userId(),
            $amount,
            $reason,
            $now,
            $eventId,
            $metadata,
        );
    }

    /**
     * Deleting the account does not settle its debt and does not remove its
     * movements (`C-21`): accounting-wise an unpaid debt is issuance, and
     * pretending otherwise would break `RN-11`.
     */
    public function anonymise(\DateTimeImmutable $now): void
    {
        $this->anonymisedAt = $now;
        $this->updatedAt = $now;
    }
}
