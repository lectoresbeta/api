<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Alguien se llama distinto (`FEAT-USR-034` `RN-10`).
 *
 * Lleva **los dos nombres y la caducidad del alias**, que es justo lo que
 * necesita un read model con el `@` copiado: el viejo para encontrar lo que
 * hay que actualizar, el nuevo para escribirlo, y la fecha para saber hasta
 * cuándo un enlace antiguo seguirá llevando a alguna parte.
 *
 * **Recuperar un nombre publica este mismo evento**: para quien lo consume es
 * un cambio más, y darle dos hechos para la misma consecuencia solo le
 * obligaría a tratarlos igual.
 *
 * `userId` no cambia nunca, y esa es la garantía que hace todo esto seguro:
 * el nombre de usuario es presentación y enrutado, jamás identidad.
 */
final readonly class UsernameChanged implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private string $previousUsername,
        private string $newUsername,
        private \DateTimeImmutable $aliasExpiresAt,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'UsernameChanged';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'previousUsername' => $this->previousUsername,
            'newUsername' => $this->newUsername,
            'aliasExpiresAt' => $this->aliasExpiresAt->format(\DATE_ATOM),
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
