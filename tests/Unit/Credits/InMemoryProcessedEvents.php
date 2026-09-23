<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;

final class InMemoryProcessedEvents implements ProcessedEventRepository
{
    /** @var array<string, true> */
    private array $seen = [];

    public function wasProcessed(string $eventId, string $consumer): bool
    {
        return isset($this->seen[$eventId.'|'.$consumer]);
    }

    public function markProcessed(ProcessedEvent $event): void
    {
        $this->seen[$event->eventId().'|'.$event->consumer()] = true;
    }

    public function purgeOlderThan(\DateTimeImmutable $moment): int
    {
        return 0;
    }
}
