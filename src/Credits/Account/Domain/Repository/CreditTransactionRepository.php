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

    /**
     * Lo que han emitido los grifos: regalos de bienvenida, recompensas de
     * invitación y ajustes manuales. Una de las tres cifras de la invariante
     * de `RN-11` (`FEAT-CRD-012`).
     */
    public function totalIssued(): int;

    /**
     * La suma de **todos** los movimientos, grifos y transferencias.
     *
     * Si las transferencias son de verdad transferencias se anulan entre sí,
     * así que esto tiene que ser igual a `totalIssued()`. Que no lo sea
     * significa que hay un movimiento que cobra sin pagar o paga sin cobrar,
     * y es la mitad de la invariante que ningún test de un caso de uso
     * concreto puede detectar.
     */
    public function totalMoved(): int;

    /**
     * Cuántos movimientos de un tipo hay en un periodo, y cuánto suman.
     *
     * Existe para los ajustes manuales (`FEAT-CRD-012`), que son la única vía
     * de crédito que no es ni transferencia ni regla automática: si hacen
     * falta muchos, algo de más arriba no está funcionando.
     *
     * @return array{count: int, net: int}
     */
    public function tallyOfReason(CreditTransactionReason $reason, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): array;
}
