<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\EventProcessing\Domain\Repository;

use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;

/**
 * The deduplication ledger (`RN-3`).
 *
 * `markProcessed` returns false when the event had already been applied, so
 * the caller can drop it without effect. The check and the record have to
 * happen inside the same transaction as the movement they guard.
 */
interface ProcessedEventRepository
{
    public function wasProcessed(string $eventId): bool;

    public function markProcessed(ProcessedEvent $event): void;

    /**
     * Rows older than this are of no use: an event will not be redelivered
     * weeks later. Without a purge the table grows for ever.
     */
    public function purgeOlderThan(\DateTimeImmutable $moment): int;
}
