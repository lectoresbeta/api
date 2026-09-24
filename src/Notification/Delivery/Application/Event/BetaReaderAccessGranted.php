<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `BetaReaderAccessGranted`, tal y como lo modela `Notification`.
 *
 * Alguien ya puede leer una obra. El aviso es **para el lector**, que estaba
 * esperando respuesta.
 *
 * `grantedVia` es el campo que decide si hay aviso, y por eso se modela
 * aunque no aparezca en ninguna frase: el hecho es el mismo por los tres
 * caminos de acceso, pero **en dos de ellos el destinatario es quien lo
 * provocó** —acepta una invitación, o empieza a corregir una obra pública— y
 * ahí avisarle sería contarle lo que acaba de hacer.
 *
 * No se modela como enum a propósito: es un valor que define `Reading`, y
 * copiar aquí su lista obligaría a tocar este contexto cada vez que aparezca
 * un camino nuevo. Lo que este contexto necesita saber es una pregunta más
 * estrecha —si lo provocó el lector—, y esa la responde `causedByTheReader()`.
 */
final readonly class BetaReaderAccessGranted implements IncomingIntegrationEvent
{
    /**
     * El único camino en el que el acceso lo concede otra persona. Los demás
     * los inicia el propio lector.
     */
    private const GRANTED_BY_THE_AUTHOR = 'REQUEST_APPROVED';

    private function __construct(
        public string $accessId,
        public string $workId,
        public string $authorId,
        public string $readerId,
        public string $grantedVia,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'BetaReaderAccessGranted';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        return new self(
            self::text($payload, 'accessId'),
            self::text($payload, 'workId'),
            self::text($payload, 'authorId'),
            self::text($payload, 'readerId'),
            self::text($payload, 'grantedVia'),
            $eventId,
            $occurredAt,
        );
    }

    /**
     * Si el acceso lo puso en marcha el propio lector.
     *
     * Se pregunta en negativo —todo lo que no sea una solicitud aprobada lo
     * inició él— para que un camino nuevo de `Reading` no genere avisos sin
     * que nadie lo haya decidido. Un aviso de menos se nota y se arregla; uno
     * de más enseña a ignorar la campana.
     */
    public function causedByTheReader(): bool
    {
        return self::GRANTED_BY_THE_AUTHOR !== $this->grantedVia;
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
            'authorId' => $this->authorId,
            'readerId' => $this->readerId,
            'grantedVia' => $this->grantedVia,
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
