<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\AuditLog\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Moderation\AuditLog\Domain\Entity\AuditEntry;
use LectoresBeta\Moderation\AuditLog\Domain\Repository\AuditEntryRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<AuditEntry>
 */
final class DoctrineAuditEntryRepository extends DoctrineRepository implements AuditEntryRepository
{
    public function add(AuditEntry $entry): void
    {
        $this->register($entry);
    }

    protected function entityClass(): string
    {
        return AuditEntry::class;
    }
}
