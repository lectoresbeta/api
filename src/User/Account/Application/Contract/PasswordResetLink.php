<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * Lo que otro contexto necesita para mandar un enlace de recuperación.
 *
 * Datos, y no el agregado `User`: quien recibe una entidad acaba navegando
 * por ella, y el modelo interno vuelve a estar compartido.
 *
 * `token` es el secreto en claro, de un solo uso. Aquí es seguro y en un
 * evento de integración no lo sería: este valor se entrega en proceso y muere
 * con la petición, mientras que un mensaje en RabbitMQ se persiste, se
 * reintenta y acaba en una cola de fallos que alguien mira.
 */
final readonly class PasswordResetLink
{
    public function __construct(
        public string $email,
        public string $username,
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
