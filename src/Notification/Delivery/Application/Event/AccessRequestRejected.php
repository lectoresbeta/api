<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `AccessRequestRejected`, tal y como lo modela `Notification`.
 *
 * El autor ha dicho que no. El aviso es **para quien preguntó**: estaba
 * esperando respuesta, y no recibir ninguna es peor que un no.
 *
 * Lleva la obra aunque quien preguntó ya sepa cuál es: la bandeja enseña
 * varios avisos juntos, y «tu solicitud ha sido rechazada», sin más, obliga a
 * abrir cada uno para saber de qué habla.
 *
 * No lleva motivo porque no hay: rechazar no lo exige
 * (`FEAT-RDG-003` `RN-5`).
 */
final readonly class AccessRequestRejected implements IncomingIntegrationEvent
{
    private function __construct(
        public string $accessRequestId,
        public string $workId,
        public string $readerId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'AccessRequestRejected';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        return new self(
            self::text($payload, 'accessRequestId'),
            self::text($payload, 'workId'),
            self::text($payload, 'readerId'),
            $eventId,
            $occurredAt,
        );
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
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'accessRequestId' => $this->accessRequestId,
            'workId' => $this->workId,
            'readerId' => $this->readerId,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function text(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;

        if (!\is_string($value) || '' === $value) {
            throw new \InvalidArgumentException(\sprintf('%s carries no %s.', self::subscribesTo(), $field));
        }

        return $value;
    }
}
