<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\EmailChangeRequestId;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Alguien ha pedido cambiar el correo de su cuenta (`FEAT-USR-040`).
 *
 * **Ninguna de las dos direcciones viaja**, ni la vieja ni la nueva. Un
 * evento que reparte direcciones de correo por la cola es una filtración
 * esperando a ocurrir: la cola persiste, reintenta y aparca mensajes que
 * alguien acaba leyendo. `Notification` las obtiene por contrato al enviar,
 * y el resto de contextos no tiene nada que hacer con ellas.
 *
 * Tampoco el token, por lo mismo de siempre: es una credencial viva.
 */
final readonly class EmailChangeRequested implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private EmailChangeRequestId $requestId,
        private \DateTimeImmutable $requestedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'EmailChangeRequested';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'requestId' => $this->requestId->value(),
            'requestedAt' => $this->requestedAt->format(\DATE_ATOM),
        ];
    }
}
