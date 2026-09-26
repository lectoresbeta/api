<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Event;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * La congelación se ha levantado **antes de su plazo** (`FEAT-MOD-006`
 * `RN-3`, `RN-9`).
 *
 * Se publica solo cuando alguien decide levantar la sanción, nunca cuando el
 * plazo vence: para eso está la fecha que viajó en `CreditDebtFrozen`, y un
 * hecho por cada vencimiento exigiría un proceso que recorriera la tabla
 * buscándolos — un temporizador que puede no ejecutarse, para decir algo que
 * quien escucha ya sabía.
 *
 * La deuda no se ha saldado: vuelve a retener, que es exactamente lo que
 * `RN-9` quiere decir con «vuelve a contar».
 */
final readonly class CreditDebtThawed implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private \DateTimeImmutable $thawedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CreditDebtThawed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->thawedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'thawedAt' => $this->thawedAt->format(\DATE_ATOM),
        ];
    }
}
