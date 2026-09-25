<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Contract;

/**
 * Cuántos avisos sin leer tiene alguien
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * Existe para el contexto de sesión ([`FEAT-USR-027`](../../../../../docs/features/user/FEAT-USR-027-session-context.md)),
 * que pinta el punto de la campana en todas las pantallas. Sin él, cada
 * navegación serían dos peticiones para un número.
 *
 * Pregunta y devuelve un entero: ni la bandeja, ni los avisos, ni nada por lo
 * que se pueda navegar hasta ellos. Lo que se puede leer se lee por
 * `GET /me/notifications`, con su sesión y sus reglas.
 */
interface UnreadNotificationCount
{
    public function forRecipient(string $userId): int;
}
