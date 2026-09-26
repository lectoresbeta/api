<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Port;

/**
 * Lo que el contexto de sesión pide **a otros contextos**, tras un puerto.
 *
 * Existe para que `RN-7` sea posible: si el contexto de al lado no responde,
 * el resto de la respuesta se sirve igual, y quien decide eso es quien sabe
 * de fallos —Infrastructure— y no el caso de uso.
 *
 * Por eso devuelve `null` y no lanza: para este handler, «no lo sé» es una
 * respuesta válida y un fallo no lo sería.
 */
interface SessionSideloads
{
    public function unreadNotifications(string $userId): ?int;
}
