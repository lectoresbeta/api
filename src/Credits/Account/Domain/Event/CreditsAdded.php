<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Event;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Credits went into an account (`FEAT-CRD-006`).
 *
 * The amount travels here because **`Credits` is the one publishing it**.
 * That is the direction the isolation rule protects: no other context may
 * send an amount in, and this one may say what it decided.
 *
 * `reason` is the business name of the movement — `CORRECTION_EARNED`,
 * `WELCOME_GRANT` — so a consumer can word the notice without asking.
 */
final readonly class CreditsAdded implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private int $amount,
        private string $reason,
        private int $balance,
        private \DateTimeImmutable $addedAt,
        /**
         * La corrección a la que se refiere el movimiento, cuando la hay.
         *
         * Es una **referencia**, no un dato de nadie: permite a quien lo
         * escucha decir por qué se movió el saldo sin preguntarle nada a
         * `Credits`, que no responde preguntas de nadie.
         */
        private ?string $correctionId = null,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CreditsAdded';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'amount' => $this->amount,
            'reason' => $this->reason,
            'balance' => $this->balance,
            'correctionId' => $this->correctionId,
            'addedAt' => $this->addedAt->format(\DATE_ATOM),
        ];
    }
}
