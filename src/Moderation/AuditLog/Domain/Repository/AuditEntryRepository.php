<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\AuditLog\Domain\Repository;

use LectoresBeta\Moderation\AuditLog\Domain\Entity\AuditEntry;

interface AuditEntryRepository
{
    public function add(AuditEntry $entry): void;
}
