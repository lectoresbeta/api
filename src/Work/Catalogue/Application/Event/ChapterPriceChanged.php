<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ChapterPriceChanged`, tal y como lo modela `Work` (`FEAT-CRD-013`).
 *
 * Es el único hecho con un importe que el catálogo recibe, y llega **ya
 * traducido a créditos**: `RN-1` de la ficha pide justamente eso, que la
 * traducción sea de `Credits` y de nadie más. `Work` copia el número y no
 * sabe de dónde sale, que es lo que mantiene la insignia fuera de su modelo.
 *
 * La cifra es lo que gana quien corrija el capítulo. Que su autor pueda
 * pagarla se dice en otro hecho, `ChapterCorrectabilityChanged`, y por eso
 * esta clase no decide nada sobre si el capítulo se enseña.
 */
final readonly class ChapterPriceChanged implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public int $credits,
        private string $eventId,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ChapterPriceChanged';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $credits = $payload['credits'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId) || !\is_int($credits)) {
            throw new \InvalidArgumentException('ChapterPriceChanged carries no chapter, work or price.');
        }

        return new self($chapterId, $workId, max(0, $credits), $eventId, $occurredAt);
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
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'credits' => $this->credits,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
