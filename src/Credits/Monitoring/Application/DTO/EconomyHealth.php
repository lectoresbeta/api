<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Monitoring\Application\DTO;

use LectoresBeta\Credits\Monitoring\Domain\ValueObject\AccountingCheck;

/**
 * El estado de la economía de créditos (`FEAT-CRD-012`).
 *
 * **No es un panel bonito: es el instrumento que dice cuál de las palancas
 * hay que mover.** Un sistema de créditos sin medición es un sistema que se
 * descubre roto por las quejas, y las dos formas de morir —nadie tiene
 * créditos, o los créditos no valen nada— tardan semanas en manifestarse y
 * son caras de revertir, porque para entonces los saldos ya están formados.
 *
 * Mezcla dos clases de cifra y conviene no confundirlas: la invariante y el
 * reparto de saldos son **una foto de ahora**; los ajustes manuales son de un
 * **periodo**. Por eso el periodo viaja en la respuesta: sin él, quien la lea
 * no sabe de cuándo habla la mitad de lo que ve.
 */
final readonly class EconomyHealth
{
    /**
     * @param array{total: int, atZeroOrBelow: int, inDebt: int, deepestDebt: int} $accounts
     * @param array{granted: int, settled: int}                                    $overdraft
     * @param array{count: int, net: int}                                          $manualAdjustments
     * @param list<string>                                                         $alerts
     */
    public function __construct(
        public AccountingCheck $invariant,
        public ?string $from,
        public ?string $to,
        public array $accounts,
        public array $overdraft,
        public array $manualAdjustments,
        public int $correctableChapters,
        public array $alerts,
    ) {
    }

    /**
     * Las alertas se calculan **sobre la foto ya montada**, no a la vez que
     * ella: un umbral se compara con una cifra, y la cifra tiene que existir
     * antes. Devolver una copia y no mutar mantiene el DTO inmutable.
     *
     * @param list<string> $alerts
     */
    public function withAlerts(array $alerts): self
    {
        return new self(
            $this->invariant,
            $this->from,
            $this->to,
            $this->accounts,
            $this->overdraft,
            $this->manualAdjustments,
            $this->correctableChapters,
            $alerts,
        );
    }

    /**
     * La proporción de cuentas a cero o en rojo, que es la métrica de
     * concentración: si sube, el saldo se está acumulando en pocas manos.
     */
    public function shareAtZeroOrBelow(): float
    {
        return 0 === $this->accounts['total']
            ? 0.0
            : round($this->accounts['atZeroOrBelow'] / $this->accounts['total'], 4);
    }

    /**
     * Qué proporción de los descubiertos concedidos se ha saldado.
     *
     * Nula mientras no se haya concedido ninguno: un cero diría que ninguno
     * se recupera, que es una afirmación sobre datos que no existen.
     */
    public function overdraftRecoveryRate(): ?float
    {
        return 0 === $this->overdraft['granted']
            ? null
            : round($this->overdraft['settled'] / $this->overdraft['granted'], 4);
    }
}
