<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Repository;

use LectoresBeta\Credits\Account\Domain\Entity\CreditTransaction;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

/**
 * Movements are immutable, so this contract offers **no update and no
 * delete** (`RN-2`). Correcting one means adding another.
 */
interface CreditTransactionRepository
{
    public function add(CreditTransaction $transaction): void;

    /**
     * El historial, de lo más reciente a lo más antiguo (`FEAT-CRD-008`).
     *
     * @return list<CreditTransaction>
     */
    public function historyOf(
        UserId $userId,
        int $limit = 50,
        int $offset = 0,
        ?CreditTransactionReason $reason = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array;

    /**
     * La suma de lo que se movió **después** de ese apunte.
     *
     * Es lo que permite reconstruir el saldo con el que quedó cada fila sin
     * guardarlo: un dato derivado almacenado puede acabar contradiciendo a la
     * suma, que es justo el fallo que `RN-11` existe para detectar.
     */
    public function sumAfter(UserId $userId, CreditTransaction $movement): int;

    /**
     * Recomputes a balance from its movements. It exists so that the running
     * total on the account can be checked against the only thing that is
     * really true (`RN-1`).
     */
    public function balanceOf(UserId $userId): int;

    /**
     * Whether this account has ever had a movement for this reason.
     *
     * Exists for the invariants that are «once per user, for ever» rather
     * than «once per event»: the welcome grant is one (`FEAT-CRD-002`
     * `RN-3`), and deduplicating by event id does not cover it.
     */
    public function hasMovementWithReason(UserId $userId, CreditTransactionReason $reason): bool;

    /**
     * Everything the economy has ever issued: the welcome grants, the
     * invitation rewards and any manual adjustment. The other half of the
     * invariant in `RN-11`.
     */
    /**
     * Los movimientos de una corrección concreta, en el orden en que
     * ocurrieron.
     *
     * Existe para la reversión de `FEAT-MOD-002`: revertir no edita ni borra
     * nada —un movimiento es inmutable— sino que añade dos apuntes nuevos, y
     * para eso hay que saber **quién pagó, quién cobró y cuánto**. Y cuánto
     * es el importe que se cobró entonces, no el precio vigente del capítulo,
     * que puede haber cambiado.
     *
     * @return list<CreditTransaction>
     */
    public function ofCorrection(string $correctionId): array;

    public function totalIssued(): int;
}
