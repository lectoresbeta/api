<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Service;

/**
 * Las cifras de la propina (`FEAT-CRD-017` `RN-3`).
 *
 * De uno a cinco créditos. Por encima de cinco la propina se parece al precio
 * de una corrección y deja de leerse como un extra: lo que la hace valer es
 * que sea un gesto, no una segunda tarifa.
 *
 * **Sin tope por autor ni por periodo** (`RN-3b`): es su saldo, y el sistema
 * no pierde nada porque la propina es una transferencia, no un grifo.
 */
final class TipPolicy
{
    public const MINIMUM = 1;

    public const MAXIMUM = 5;

    public static function admits(int $amount): bool
    {
        return $amount >= self::MINIMUM && $amount <= self::MAXIMUM;
    }
}
