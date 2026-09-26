<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `SanctionLifted`, tal y como lo modela `Credits` (`FEAT-MOD-006` `RN-3`).
 *
 * Solo llega cuando alguien decide levantar una sanción antes de tiempo. Las
 * de plazo fijo terminan por su fecha y no publican nada, que es justo lo que
 * permite que aquí la congelación caduque sola.
 *
 * El hecho no dice de qué tipo era la sanción que se levanta, y no hace falta:
 * descongelar una deuda que no estaba congelada no es nada.
 */
final readonly class SanctionLifted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        private string $eventId,
        private \DateTimeImmutable $liftedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'SanctionLifted';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('SanctionLifted carries no userId.');
        }

        return new self($userId, $eventId, $occurredAt);
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function eventName(): string
    {
        return self::subscribesTo();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->liftedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'liftedAt' => $this->liftedAt->format(\DATE_ATOM),
        ];
    }
}
