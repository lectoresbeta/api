<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `PublicCorrectionSubmitted`, tal y como lo modela `Notification`.
 *
 * Alguien **sin cuenta** ha corregido por un enlace que el autor repartió. El
 * aviso es para él, que es quien lo estaba esperando.
 *
 * Sin `readerId`, y eso cambia el aviso: no hay perfil que enseñar ni a quien
 * enlazar. Lo único que identifica a quien escribió es `authorLabel`, el
 * nombre que tecleó, **que nadie ha verificado** — así que viaja como
 * etiqueta y el aviso no puede presentarlo como si fuera un usuario.
 */
final readonly class PublicCorrectionSubmitted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $correctionId,
        public string $workId,
        public string $chapterId,
        public string $authorId,
        public ?string $authorLabel,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'PublicCorrectionSubmitted';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $label = $payload['authorLabel'] ?? null;

        return new self(
            self::text($payload, 'correctionId'),
            self::text($payload, 'workId'),
            self::text($payload, 'chapterId'),
            self::text($payload, 'authorId'),
            \is_string($label) && '' !== $label ? $label : null,
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
            'correctionId' => $this->correctionId,
            'workId' => $this->workId,
            'chapterId' => $this->chapterId,
            'authorId' => $this->authorId,
            'authorLabel' => $this->authorLabel,
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
