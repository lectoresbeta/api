<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Monitoring\Domain\ValueObject;

/**
 * El resultado de comprobar la invariante contable (`FEAT-CRD-012`,
 * `decision:0006` `RN-11`).
 *
 * Tres cifras que tienen que coincidir, y **coincidir por dos caminos
 * distintos**:
 *
 * - `issued` es lo que han emitido los grifos: regalos de bienvenida,
 *   recompensas de invitación y ajustes manuales. Son los únicos movimientos
 *   que crean o destruyen créditos;
 * - `moved` es la suma de **todos** los movimientos. Si las transferencias
 *   son de verdad transferencias, se anulan entre sí y esta cifra tiene que
 *   ser igual a la anterior. Que no lo sea significa que hay un movimiento
 *   que cobra sin pagar o paga sin cobrar;
 * - `balances` es la suma de los saldos guardados. Se calcula sin mirar los
 *   movimientos, así que comparar con `moved` es lo que detecta un saldo que
 *   se ha desviado de su propia historia.
 *
 * **Por qué no aparece el descubierto.** La ficha escribe la invariante como
 * «saldos = grifos − descubierto no recuperado», y ese término sobra: los
 * saldos negativos ya están dentro de la suma, así que restarlos otra vez
 * haría fallar la identidad justo cuando alguien está endeudado, que es un
 * estado normal y previsto (`FEAT-CRD-018`). La igualdad exacta es más fuerte
 * y más fácil de comprobar.
 */
final readonly class AccountingCheck
{
    public function __construct(
        public int $issued,
        public int $moved,
        public int $balances,
    ) {
    }

    public function holds(): bool
    {
        return $this->issued === $this->moved && $this->moved === $this->balances;
    }

    /**
     * Qué mitad ha fallado, que es lo primero que hace falta saber.
     *
     * @return 'TRANSFERS_DO_NOT_NET_TO_ZERO'|'BALANCES_DRIFTED_FROM_HISTORY'|null
     */
    public function failure(): ?string
    {
        if ($this->issued !== $this->moved) {
            return 'TRANSFERS_DO_NOT_NET_TO_ZERO';
        }

        if ($this->moved !== $this->balances) {
            return 'BALANCES_DRIFTED_FROM_HISTORY';
        }

        return null;
    }
}
