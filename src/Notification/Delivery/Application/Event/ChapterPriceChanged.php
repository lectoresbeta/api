<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ChapterPriceChanged`, tal y como lo modela `Notification`
 * (`FEAT-CRD-016` `RN-9`, `C-15`).
 *
 * Se escucha por **una sola cosa**: avisar al autor cuando ampliar un
 * capítulo lo ha encarecido. De ahí que importe `previousCredits`, que viene
 * nulo cuando el capítulo estrena precio — y estrenar precio no es
 * encarecerse.
 *
 * El hecho no dice de quién es la obra, y no tiene por qué: lo pregunta el
 * consumidor por el contrato publicado de `Work`, como todos los demás.
 */
final readonly class ChapterPriceChanged implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public int $credits,
        public ?int $previousCredits,
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
        $previousCredits = $payload['previousCredits'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId) || !\is_int($credits)) {
            throw new \InvalidArgumentException('ChapterPriceChanged carries no chapter, work or price.');
        }

        return new self(
            $chapterId,
            $workId,
            $credits,
            \is_int($previousCredits) ? $previousCredits : null,
            $eventId,
            $occurredAt,
        );
    }

    /**
     * `RN-9` habla de **encarecer**, no de cambiar. Un capítulo que se abarata
     * no interrumpe a nadie, y uno que estrena precio tampoco: no había antes.
     */
    public function isMoreExpensive(): bool
    {
        return null !== $this->previousCredits && $this->credits > $this->previousCredits;
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
            'previousCredits' => $this->previousCredits,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
