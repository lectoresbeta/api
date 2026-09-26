<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Query;

/**
 * Mi bandeja (`FEAT-NOT-009`).
 *
 * No hay parámetro de destinatario, y no es un descuido: **quien pregunta es
 * quien tiene la sesión**. Un identificador aquí sería una forma de pedir la
 * bandeja de otra persona, y el mejor sitio para que eso no pase es donde
 * nunca se puede escribir.
 */
final readonly class ListMyNotifications
{
    public function __construct(
        public string $userId,
        public bool $unreadOnly,
        public ?int $limit,
        public ?string $cursor,
    ) {
    }
}
