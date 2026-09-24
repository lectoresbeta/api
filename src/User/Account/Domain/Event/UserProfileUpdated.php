<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Alguien ha cambiado lo que enseña de sí mismo (`FEAT-USR-008`).
 *
 * Lleva **los valores nuevos y no un diff**, porque quien lo consume no
 * quiere saber qué cambió sino con qué quedarse: el nombre y el avatar
 * aparecen copiados en el muro, en los comentarios, en el catálogo y en las
 * notificaciones, y un consumidor que recibiera «cambió el nombre» tendría
 * que preguntar cuál es ahora.
 *
 * Ni correo ni fecha de nacimiento, nunca: son privados y no salen de este
 * contexto (`FEAT-USR-022` `RN-4b`). Lo que viaja es exactamente lo que
 * cualquiera puede ver abriendo el perfil.
 */
final readonly class UserProfileUpdated implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private ?string $name,
        private ?string $description,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'UserProfileUpdated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'name' => $this->name,
            'description' => $this->description,
            'updatedAt' => $this->updatedAt->format(\DATE_ATOM),
        ];
    }
}
