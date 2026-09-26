<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Query;

/**
 * Cuántos avisos sin leer tengo (`FEAT-NOT-009` `RN-3`).
 *
 * Operación aparte de la bandeja porque la campana de la cabecera se pinta en
 * todas las pantallas: cargar veinte filas para enseñar un `2` sería pagar la
 * lista entera en cada navegación.
 */
final readonly class CountMyUnreadNotifications
{
    public function __construct(public string $userId)
    {
    }
}
