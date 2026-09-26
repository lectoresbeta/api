<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\EventProcessing\Domain\Repository;

use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;

/**
 * The deduplication ledger (`FEAT-CRD-011`).
 *
 * The check and the record have to happen inside the same transaction as the
 * movement they guard, and both are keyed by `(eventId, consumer)`: a fact is
 * applied at most once **per rule**, not once in total.
 */
interface ProcessedEventRepository
{
    public function wasProcessed(string $eventId, string $consumer): bool;

    public function markProcessed(ProcessedEvent $event): void;

    /**
     * Rows older than this are of no use: an event will not be redelivered
     * weeks later. Without a purge the table grows for ever.
     *
     * Deletes **at most `$batch` rows** and answers how many it deleted, so
     * the caller can repeat until it runs out. One unbounded `DELETE` would
     * lock the table that every credit-moving consumer reads before applying
     * anything, and a crash halfway would roll back work already done.
     */
    public function purgeOlderThan(\DateTimeImmutable $moment, int $batch): int;
}
