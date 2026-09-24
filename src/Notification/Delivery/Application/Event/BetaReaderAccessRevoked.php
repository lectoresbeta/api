<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `BetaReaderAccessRevoked`, tal y como lo modela `Notification`.
 *
 * Alguien ha dejado de poder leer una obra, y **eso hay que decírselo**.
 *
 * Es el aviso que saldó la deuda que dos fichas habían escrito:
 * [`FEAT-COM-034`](../../../../../docs/features/community/FEAT-COM-034-block-user.md)
 * y [`FEAT-RDG-010`](../../../../../docs/features/reading/FEAT-RDG-010-revoke-beta-reader-access.md)
 * `R-13`. Quien estaba corrigiendo **pierde ese trabajo**, y descubrirlo sin
 * explicación es mucho peor que que te lo digan.
 *
 * El hecho no dice por cuál de los tres caminos se retiró —descartar, un
 * bloqueo o la decisión del autor— y el aviso tampoco: contar que le han
 * bloqueado sería avisar de un bloqueo, que es justo lo que `FEAT-COM-034`
 * `RN-2` evita.
 */
final readonly class BetaReaderAccessRevoked implements IncomingIntegrationEvent
{
    private function __construct(
        public string $accessId,
        public string $workId,
        public string $readerId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'BetaReaderAccessRevoked';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        return new self(
            self::text($payload, 'accessId'),
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
            'accessId' => $this->accessId,
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
