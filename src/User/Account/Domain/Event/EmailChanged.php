<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * El correo de una cuenta ha cambiado, ya confirmado (`FEAT-USR-040`).
 *
 * Sin la dirección, ni la nueva ni la anterior. Quien necesite escribir a esa
 * cuenta pregunta por contrato en el momento de hacerlo, que además es lo
 * único correcto: una dirección guardada por otro contexto es una copia que
 * envejece.
 */
final readonly class EmailChanged implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'EmailChanged';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
