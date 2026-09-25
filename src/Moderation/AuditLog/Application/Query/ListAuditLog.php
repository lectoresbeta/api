<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\AuditLog\Application\Query;

final readonly class ListAuditLog
{
    public function __construct(
        public ?string $actorId = null,
        public ?string $action = null,
        public ?string $targetType = null,
        public ?string $targetId = null,
        public ?string $from = null,
        public ?string $to = null,
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
