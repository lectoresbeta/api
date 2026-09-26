<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Port;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

/**
 * Avisar a quien opera la plataforma de algo que la economía no predice.
 *
 * Es un puerto y no un logger porque **Application no sabe dónde acaban estos
 * avisos**: hoy en el registro, mañana quizá en el panel de salud de
 * [`FEAT-CRD-012`](../../../../../docs/features/credits/FEAT-CRD-012-economy-health.md).
 * Lo que sí sabe es qué merece contarse.
 */
interface EconomyAlert
{
    /**
     * Una deuda más honda de lo que el diseño permite (`FEAT-CRD-018`
     * `RN-12`).
     */
    public function debtRanDeeperThanExpected(UserId $userId, int $balance, int $threshold): void;
}
