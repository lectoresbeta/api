<?php

declare(strict_types=1);

namespace LectoresBeta\Community\EventProcessing\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Community\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<ProcessedEvent>
 */
final class DoctrineProcessedEventRepository extends DoctrineRepository implements ProcessedEventRepository
{
    public function wasProcessed(string $eventId, string $consumer): bool
    {
        return null !== $this->repository()->find(['eventId' => $eventId, 'consumer' => $consumer]);
    }

    public function markProcessed(ProcessedEvent $event): void
    {
        $this->register($event);
    }

    protected function entityClass(): string
    {
        return ProcessedEvent::class;
    }
}
