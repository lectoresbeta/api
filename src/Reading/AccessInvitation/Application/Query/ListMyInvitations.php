<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Query;

final readonly class ListMyInvitations
{
    public function __construct(
        public string $inviteeId,
        public ?string $status,
        public ?string $cursor,
        public ?int $limit,
    ) {
    }
}
