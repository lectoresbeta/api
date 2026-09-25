<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `OverdraftCorrectionGranted`, tal y como lo modela `Notification`
 * (`FEAT-CRD-019`).
 *
 * Lo que este contexto entiende es **a quién hay que avisar y con qué cifra**.
 * `creditsNeeded` no es un adorno: el mensaje entero vive de ella. «Te faltan
 * 7 créditos» motiva mucho más que «repón saldo», porque una meta concreta se
 * puede alcanzar y una vaga no (`RN-4`, `C-31`).
 */
final readonly class OverdraftCorrectionGranted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $authorId,
        public string $correctionId,
        public string $chapterId,
        public string $workId,
        public int $creditsNeeded,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'OverdraftCorrectionGranted';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $authorId = $payload['authorId'] ?? null;

        if (!\is_string($authorId) || '' === $authorId) {
            throw new \InvalidArgumentException('OverdraftCorrectionGranted carries no author.');
        }

        $creditsNeeded = $payload['creditsNeeded'] ?? null;

        return new self(
            $authorId,
            self::textOf($payload, 'correctionId'),
            self::textOf($payload, 'chapterId'),
            self::textOf($payload, 'workId'),
            \is_int($creditsNeeded) ? $creditsNeeded : 0,
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
            'authorId' => $this->authorId,
            'correctionId' => $this->correctionId,
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'creditsNeeded' => $this->creditsNeeded,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function textOf(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        return \is_string($value) ? $value : '';
    }
}
