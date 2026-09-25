<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien ha cambiado qué avisos quiere recibir (`FEAT-USR-039`).
 *
 * **Nadie lo consume hoy, y se publica igual.** `Notification` no lo necesita
 * porque pregunta al entregar (`RN-6`), que es lo que hace que una
 * preferencia cambiada surta efecto en el acto y no cuando la cola se ponga
 * al día. Lo que este hecho da es traza: qué cambió alguien y cuándo, que es
 * lo primero que se busca cuando alguien dice que dejó de recibir avisos.
 *
 * Lleva **qué se tocó, no la configuración entera**: los ajustes de una
 * persona no tienen por qué viajar por una cola para decir que cambiaron.
 */
final readonly class NotificationPreferencesChanged implements IntegrationEvent
{
    /**
     * @param list<string> $changedTopics
     */
    public function __construct(
        private EventId $eventId,
        private string $userId,
        private array $changedTopics,
        private ?bool $allMuted,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'NotificationPreferencesChanged';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'changedTopics' => $this->changedTopics,
            'allMuted' => $this->allMuted,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
