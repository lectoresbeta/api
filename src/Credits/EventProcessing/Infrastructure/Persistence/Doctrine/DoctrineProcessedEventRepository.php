<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\EventProcessing\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
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

    public function purgeOlderThan(\DateTimeImmutable $moment): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->delete(ProcessedEvent::class, 'e')
            ->where('e.processedAt < :moment')
            ->setParameter('moment', $moment)
            ->getQuery()
            ->execute();
    }

    protected function entityClass(): string
    {
        return ProcessedEvent::class;
    }
}
