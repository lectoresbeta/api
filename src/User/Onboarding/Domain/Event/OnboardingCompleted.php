<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Alguien ha terminado de entrar (`FEAT-COM-016` `RN-8`).
 *
 * Se publica **también cuando el paso 3 se omite** por no haber autores que
 * sugerir. Lo que el hecho dice es que esa persona ya está dentro, no que
 * haya pasado por tres pantallas; si dependiera de eso, una plataforma recién
 * lanzada no completaría el onboarding de nadie.
 *
 * El identificador y nada más: quien lo consuma tiene su propia copia de las
 * personas, y copiar aquí un nombre solo añadiría un sitio donde envejece.
 */
final readonly class OnboardingCompleted implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private \DateTimeImmutable $completedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'OnboardingCompleted';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'completedAt' => $this->completedAt->format(\DATE_ATOM),
        ];
    }
}
