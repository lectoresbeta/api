<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Alguien ha pedido que le vuelvan a mandar el enlace de activación
 * (`FEAT-USR-021`).
 *
 * **No lleva el correo ni el token**, y las dos ausencias tienen su motivo.
 * El token es una credencial viva y no viaja por una cola que persiste,
 * reintenta y aparca mensajes (`decision:0014`); se pide por el contrato
 * publicado en el momento de enviar. La dirección tampoco hace falta: quien
 * envía la obtiene por ese mismo contrato, y un dato personal que no viaja es
 * un dato personal que no se filtra en un mensaje aparcado.
 *
 * Lo que sí viaja es **qué cuenta**, que es el hecho.
 */
final readonly class ActivationEmailRequested implements IntegrationEvent
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
        return 'ActivationEmailRequested';
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
