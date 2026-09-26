<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Command;

/**
 * Vaciar el contador de una vez (`FEAT-NOT-009` `RN-5`).
 *
 * **Un contador que no se puede vaciar es un contador que acaba ignorado**, y
 * una campana que siempre está roja deja de avisar de nada.
 */
final readonly class MarkAllNotificationsRead
{
    public function __construct(public string $userId)
    {
    }
}
