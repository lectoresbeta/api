<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Service;

use LectoresBeta\Credits\Account\Application\Port\EconomyAlert;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

/**
 * La alerta de operaciones cuando alguien baja de −40 (`FEAT-CRD-018`
 * `RN-12`).
 *
 * **No es un castigo al usuario ni cambia nada para él.** Es una señal para
 * quien opera la plataforma: la deuda está acotada por construcción —como
 * mucho una corrección no cubierta, y una corrección cuesta 20 a lo sumo
 * (`FEAT-CRD-016`)—, así que llegar tan abajo significa que varios lectores
 * han coincidido sobre el mismo capítulo y **el tope de correcciones
 * simultáneas no está haciendo su trabajo**.
 *
 * Por eso el aviso va hacia fuera y no hacia el autor: quien tiene que hacer
 * algo con esto es quien puede mover el tope, no quien debe los créditos.
 */
final readonly class WatchDeepDebt
{
    /**
     * No es una regla de negocio —nada cambia al cruzarlo— sino el punto a
     * partir del cual una deuda deja de parecerse a las que el diseño
     * predice.
     */
    public const ALERT_BELOW = -40;

    public function __construct(private EconomyAlert $alerts)
    {
    }

    public function check(UserId $userId, int $balance): void
    {
        if ($balance >= self::ALERT_BELOW) {
            return;
        }

        $this->alerts->debtRanDeeperThanExpected($userId, $balance, self::ALERT_BELOW);
    }
}
