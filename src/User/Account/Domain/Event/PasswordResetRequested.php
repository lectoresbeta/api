<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Alguien ha pedido el enlace para restablecer su contraseña
 * (`FEAT-USR-007`).
 *
 * **Ni el correo ni el token.** El token es una credencial viva —durante una
 * hora *es* la cuenta— y no viaja por una cola que persiste, reintenta y
 * aparca mensajes donde alguien los lee
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 * La dirección tampoco hace falta: quien envía la obtiene por el mismo
 * contrato, y un dato personal que no viaja es uno que no se filtra.
 *
 * El hecho solo se publica cuando **procede mandarlo**. Que no exista esa
 * cuenta no es un hecho de nadie, y publicarlo llenaría la cola de
 * direcciones tecleadas por desconocidos.
 */
final readonly class PasswordResetRequested implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private \DateTimeImmutable $requestedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'PasswordResetRequested';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'requestedAt' => $this->requestedAt->format(\DATE_ATOM),
        ];
    }
}
